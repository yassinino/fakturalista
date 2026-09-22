<?php

namespace App\Http\Controllers;

use App\Filament\Resources\TenantResource;
use App\Http\Requests\RegisterTrialRequest;
use App\Services\MathCaptchaService;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Public, self-service free-trial signup.
 *
 * Replaces the old "someone fills a contact form, I create their tenant
 * by hand" workflow (HomeController::freeTrial()/sendFreeTrial(), still
 * present and untouched - see routes/web.php) with fully automatic
 * provisioning, reusing the exact same TenantProvisioningService the
 * Filament admin "create tenant" wizard already relies on
 * (app/Filament/Resources/TenantResource/Pages/CreateTenant.php) - no
 * second provisioning pipeline was built.
 */
class RegisterTrialController extends Controller
{
    public function __construct(private TenantProvisioningService $provisioning) {}

    public function create(): View
    {
        return view('register', [
            'captcha'   => MathCaptchaService::generate(),
            // Reuses the exact same list the Filament admin wizard already
            // offers (TenantResource::countryOptions()) - no second,
            // possibly-diverging country list was created.
            'countries' => TenantResource::countryOptions(),
        ]);
    }

    public function store(RegisterTrialRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $email     = $validated['email'];

        // Concurrency / duplicate-submission guard: a double-click, a
        // browser retry, or two tabs submitting the same form at once must
        // never provision two tenants for the same email. This is a short,
        // best-effort lock on top of - not instead of - the hard DB-level
        // protection: `domains.domain` is UNIQUE, so even if two requests
        // both raced past this lock, only one Tenant::create()+domains()
        // ->create() pair could ever succeed (the loser hits the unique
        // constraint, which TenantProvisioningService already treats as a
        // provisioning failure and cleans up via its own rollback path).
        $lock = Cache::lock('register-trial:' . $email, 30);

        if (!$lock->get()) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'captcha_answer']))
                ->withErrors(['email' => 'Ya estamos procesando una solicitud para este email. Espera unos segundos e inténtalo de nuevo.']);
        }

        try {
            $ownerName = trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));
            $subdomain = $this->provisioning->generateUniqueSubdomain($validated['company_name']);

            $tenant = $this->provisioning->provision([
                'company_name'    => $validated['company_name'],
                'company_email'   => $email,
                'owner_name'      => $ownerName,
                'owner_email'     => $email,
                'admin_password'  => $validated['password'],
                'subdomain'       => $subdomain,
                'country'         => $validated['country'] ?? null,
                // Self-service always starts on the entry-level plan; an
                // admin using the Filament wizard can still pick any plan.
                'plan_slug'       => 'starter',
            ], selfService: true);

            $domain = $tenant->domains->first()?->domain;

            Log::info('Self-service trial registered', [
                'tenant_id' => $tenant->getTenantKey(),
                'domain'    => $domain,
            ]);

            return redirect()->away('https://' . $domain . '/admin/login?welcome=1&email=' . urlencode($email));

        } catch (\Throwable $e) {
            // TenantProvisioningService already logged the real exception
            // (stage, tenant id if created, everything except secrets) -
            // this is only the friendly, no-technical-detail message the
            // visitor sees. Never expose $e->getMessage() to the browser.
            Log::error('Self-service registration failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'captcha_answer']))
                ->withErrors(['email' => 'No hemos podido crear tu cuenta en este momento. Inténtalo de nuevo.']);
        } finally {
            $lock->release();
        }
    }
}
