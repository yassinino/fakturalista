<?php

namespace App\Http\Controllers;
use App\Mail\PasswordResetEmail;
use App\Models\CompanyProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Auth;
use Validator;

class AuthController extends Controller
{

    // ── Forgot password ────────────────────────────────────

    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Always respond with success to prevent user enumeration
        if (!$user) {
            return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetUrl = config('app.url')
            . '/admin/reset-password?token=' . $token
            . '&email=' . urlencode($request->email);

        try {
            Mail::to($user->email, $user->name)->send(new PasswordResetEmail($user, $resetUrl));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send the reset email. Please try again.',
            ], 500);
        }

        return response()->json(['message' => 'Reset link sent! Check your inbox.']);
    }

    // ── Reset password ─────────────────────────────────────

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Invalid or expired reset link. Please request a new one.',
            ], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'This reset link has expired. Please request a new one.',
            ], 422);
        }

        if (!Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'Invalid or expired reset link. Please request a new one.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'No account found with this email address.',
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password updated successfully! You can now sign in.']);
    }

    // ── Login ──────────────────────────────────────────────

    public function login(Request $request)
    {
        $requestData = $request->all();
        $validator = Validator::make($requestData,[
            'email' => 'email|required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        if(! auth()->attempt($requestData)){
            return response()->json(['error' => 'UnAuthorised Access'], 401);
        }

        $accessToken = auth()->user()->createToken('authToken')->accessToken;

        return response(['data' => [
            'user'        => auth()->user(),
            'accessToken' => $accessToken,
            'billing'     => $this->billingState(),
        ]], 200);
    }

    public function user(Request $request){
        $user = $request->user();
        return response([
            'user'    => $user,
            'billing' => $this->billingState(),
        ], 200);
    }

    /**
     * Build the billing/onboarding state object sent to the frontend on login and /user.
     * Reads from both tenant DB (CompanyProfile) and central DB (Tenant) so the Vue
     * app always has fresh state without a second round-trip.
     */
    private function billingState(): array
    {
        $tenant  = tenancy()->tenant;
        $profile = CompanyProfile::first();

        $onboardingDone   = $profile?->onboarding_completed_at !== null;
        $status           = $tenant?->subscription_status;
        $trialEndsAt      = $tenant?->trial_ends_at;
        $trialDaysLeft    = $tenant?->trialDaysLeft();
        $isReadOnly       = $tenant?->isReadOnly() ?? false;
        $showTrialBanner  = $tenant?->showTrialBanner() ?? false;

        return [
            'onboarding_completed' => $onboardingDone,
            'subscription_status'  => $status,
            'trial_ends_at'        => $trialEndsAt?->toIso8601String(),
            'trial_days_left'      => $trialDaysLeft,
            'is_read_only'         => $isReadOnly,
            'show_trial_banner'    => $showTrialBanner,
        ];
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $supportedLocales = config('app.supported_locales', ['es', 'fr', 'en']);

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'locale' => ['sometimes', 'required', 'string', 'max:10', Rule::in($supportedLocales)],
            'avatar' => 'nullable|image|max:2048',
        ];

        // Only enforce password fields when a new password is provided
        if ($request->filled('password')) {
            $rules['current_password'] = 'required|string';
            $rules['password'] = 'required|min:6|confirmed';
            $rules['password_confirmation'] = 'required|min:6';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }


        if ($request->filled('password')) {
            if (!Hash::check($request->input('current_password'), $user->password)) {
                return response()->json([
                    'errors' => [
                        'current_password' => ['La contraseña actual no coincide.']
                    ]
                ], 422);
            }
        }



        if ($request->filled('name')) {
            $user->name = $request->input('name');
        }

        if ($request->filled('email')) {
            $user->email = $request->input('email');
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }

        if ($request->filled('locale')) {
            $user->locale = $request->input('locale');
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_path = $avatarPath;
        }

        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $user,
        ], 200);
    }
}
