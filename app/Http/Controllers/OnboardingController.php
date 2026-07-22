<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    /**
     * Return current profile data so the wizard can pre-fill any partial data.
     */
    public function show(): JsonResponse
    {
        $profile = CompanyProfile::first();

        return response()->json([
            'profile'              => $profile,
            'onboarding_completed' => $profile?->onboarding_completed_at !== null,
        ]);
    }

    /**
     * Submit the onboarding wizard.
     * Saves company fields, marks onboarding complete, and starts the 3-month trial
     * on the central Tenant record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Mandatory
            'owner_name'   => 'required|string|max:255',
            'trade_name'   => 'required|string|max:255',
            'address_line1'=> 'required|string|max:255',
            'city'         => 'required|string|max:255',
            'postal_code'  => 'required|string|max:20',
            'country'      => 'required|string|max:100',
            'currency'     => 'required|string|size:3',
            // Optional
            'legal_name'   => 'nullable|string|max:255',
            'tax_id'       => 'nullable|string|max:100',
            'vat_number'   => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:50',
            'logo'         => 'nullable|image|max:2048',
        ]);

        $profile = CompanyProfile::firstOrCreate([], ['legal_name' => '']);

        // Guard: if already completed, return success without changing anything.
        if ($profile->onboarding_completed_at !== null) {
            return response()->json([
                'message'              => 'Onboarding already completed.',
                'onboarding_completed' => true,
            ]);
        }

        $profileData = [
            'trade_name'   => $validated['trade_name'],
            'legal_name'   => $validated['legal_name'] ?? $validated['trade_name'],
            'address_line1'=> $validated['address_line1'],
            'city'         => $validated['city'],
            'postal_code'  => $validated['postal_code'],
            'country'      => $validated['country'],
            'currency'     => strtoupper($validated['currency']),
            'tax_id'       => $validated['tax_id'] ?? null,
            'vat_number'   => $validated['vat_number'] ?? null,
            'phone'        => $validated['phone'] ?? null,
            'onboarding_completed_at' => now(),
        ];

        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $profileData['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $profile->update($profileData);

        // Also store the owner's full name on their User record.
        if (!empty($validated['owner_name'])) {
            $request->user()?->update(['name' => $validated['owner_name']]);
        }

        // Start the 3-month free trial on the central Tenant record.
        $tenant = tenancy()->tenant;
        $tenant->update([
            'subscription_status' => 'trialing',
            'trial_ends_at'       => now()->addMonths(3),
        ]);

        Log::info('Onboarding completed', [
            'tenant_id'      => $tenant->id,
            'trial_ends_at'  => $tenant->trial_ends_at,
        ]);

        return response()->json([
            'message'              => 'Setup complete. Your 3-month free trial has started.',
            'onboarding_completed' => true,
            'trial_ends_at'        => $tenant->trial_ends_at,
        ]);
    }
}
