<?php

namespace App\Http\Controllers;
use App\Models\Country;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Validator;
use App\Mail\ContactMessage;
use App\Mail\FreeTrialRequest;
use App\Services\MathCaptchaService;

class HomeController extends Controller
{

    public function index()
    {
        $locale = app()->getLocale();

        $plans = Plan::on('mysql')
            ->where('active', true)
            ->with(['marketingItems' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('index', compact('plans', 'locale'));
    }

    public function contact()
    {
        return view('contact', ['captcha' => MathCaptchaService::generate()]);
    }

    public function freeTrial()
    {
        return view('free-trial', ['captcha' => MathCaptchaService::generate()]);
    }

    public function verifactu()
    {
        return view('verifactu');
    }

    public function pricing()
    {
        $locale = app()->getLocale();

        $plans = Plan::on('mysql')
            ->where('active', true)
            ->with(['limits', 'marketingItems' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('pricing', compact('plans', 'locale'));
    }

    public function sendContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:150',
            'content' => 'required|string|max:2000',
            'recaptcha_response' => 'nullable|string|max:2000',
            'captcha_answer' => 'required',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!MathCaptchaService::verify($request->input('captcha_answer'))) {
                $validator->errors()->add('captcha_answer', __('site.captcha.error'));
            }
        });

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Por favor revisa los campos e intenta de nuevo.',
                    'errors' => $validator->errors(),
                    'captcha' => MathCaptchaService::generate(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $subject = $validated['subject'] ?: 'Nuevo mensaje de contacto';

        try {
            Mail::to('contact@fakturalista.com')->send(new ContactMessage([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $subject,
                'content' => $validated['content'],
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]));
        } catch (\Throwable $e) {
            Log::error('Contact email send failed', [
                'error' => $e->getMessage(),
                'email' => $validated['email'],
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'No se pudo enviar el mensaje. Intenta de nuevo.',
                    'captcha' => MathCaptchaService::generate(),
                ], 500);
            }

            return back()->withErrors([
                'email' => 'No se pudo enviar el mensaje. Intenta de nuevo.',
            ])->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'error' => 0,
                'message' => 'Mensaje enviado correctamente.',
                'captcha' => MathCaptchaService::generate(),
            ]);
        }

        return back()->with('status', __('site.contact.status_success'));
    }

    public function sendFreeTrial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'company' => 'required|string|max:150',
            'recaptcha_response' => 'nullable|string|max:2000',
            'captcha_answer' => 'required',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!MathCaptchaService::verify($request->input('captcha_answer'))) {
                $validator->errors()->add('captcha_answer', __('site.captcha.error'));
            }
        });

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Por favor revisa los campos e intenta de nuevo.',
                    'errors' => $validator->errors(),
                    'captcha' => MathCaptchaService::generate(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            Mail::to('contact@fakturalista.com')->send(new FreeTrialRequest([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'company' => $validated['company'],
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]));
        } catch (\Throwable $e) {
            Log::error('Free trial email send failed', [
                'error' => $e->getMessage(),
                'email' => $validated['email'],
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'No se pudo enviar la solicitud. Intenta de nuevo.',
                    'captcha' => MathCaptchaService::generate(),
                ], 500);
            }

            return back()->withErrors([
                'email' => 'No se pudo enviar la solicitud. Intenta de nuevo.',
            ])->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'error' => 0,
                'message' => 'Solicitud enviada correctamente.',
                'captcha' => MathCaptchaService::generate(),
            ]);
        }

        return back()->with('status', 'Solicitud enviada correctamente.');
    }

    /**
     * "Already have an account? Sign in" from the public registration page
     * (resources/views/register.blade.php). Login itself is tenant-domain-
     * scoped (auth happens against a User row that lives in the TENANT
     * database - see routes/tenant.php / AuthController::login()), so a
     * visitor on the central marketing domain can't sign in directly here.
     * This is a minimal, read-only lookup by owner email - it only ever
     * redirects to that tenant's own /admin/login, it never authenticates
     * anyone itself.
     */
    public function login()
    {
        return view('login-finder');
    }

    public function findWorkspace(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Light throttling: this endpoint doesn't create anything, but it
        // does let a caller probe "does an account exist for email X" -
        // rate-limit it the same way a login form would be.
        $key = 'find-workspace:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['email' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.']);
        }
        RateLimiter::hit($key, 60);

        $email  = trim(strtolower((string) $request->input('email')));
        $domain = Tenant::where('owner_email', $email)->first()?->domains->first()?->domain;

        if (!$domain) {
            return back()
                ->withInput()
                ->withErrors(['email' => __('site.loginFinder.not_found')]);
        }

        return redirect()->away('https://' . $domain . '/admin/login?email=' . urlencode($email));
    }

    public function about()
    {
        return view('about');
    }

    public function faq()
    {
        return view('faq');
    }

    public function security()
    {
        return view('security');
    }

    public function integrations()
    {
        return view('integrations');
    }

    public function documentation()
    {
        return view('documentation');
    }

    public function helpCenter()
    {
        return view('help-center');
    }

    public function apiDocs()
    {
        return view('api');
    }

    public function changelog()
    {
        return view('changelog');
    }

    public function legalNotice()
    {
        return view('legal-notice');
    }

    public function privacyPolicy()
    {
        return view('privacy-policy');
    }

    public function terms()
    {
        return view('terms');
    }

    public function cookiePolicy()
    {
        return view('cookie-policy');
    }

    public function setLocale(Request $request)
    {
        $supported = config('app.supported_locales', ['es', 'fr', 'en']);
        $locale = $request->input('locale');

        if ($locale && in_array($locale, $supported, true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }

    public function success(Request $request)
    {
        return view('subscription.success', [
            'session_id' => $request->get('session_id')
        ]);
    }

    public function cancel()
    {
        return view('subscription.cancel');
    }
}
