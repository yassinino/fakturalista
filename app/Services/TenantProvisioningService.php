<?php

namespace App\Services;

use App\Mail\AdminNewRegistrationMail;
use App\Mail\WelcomeSelfServiceMail;
use App\Mail\WelcomeTenantMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class TenantProvisioningService
{
    /**
     * Subdomain labels that could be confused with actual app/infra routes
     * if auto-assigned to a tenant (e.g. a business literally named "API").
     * Small, deliberately short list - not a general profanity/trademark
     * filter, just the names that would be actively confusing or collide
     * with real infrastructure.
     */
    private const RESERVED_SUBDOMAINS = [
        'www', 'api', 'admin', 'app', 'mail', 'smtp', 'ftp', 'test',
        'central', 'fakturalista', 'staging', 'dev', 'localhost',
    ];

    /**
     * Turn a business name into a unique, DNS-safe subdomain label.
     *
     * Used by the self-service registration flow, where (unlike the admin
     * Filament wizard) the visitor never types a subdomain themselves -
     * see RegisterTrialController. Str::slug() normalizes accents, spaces,
     * uppercase and special characters. Collisions (including reserved
     * words) are resolved deterministically with a numeric suffix,
     * checked against the same `domains` table + unique DB constraint the
     * admin form's own uniqueness validation relies on - so this can never
     * hand out a domain that create() would then fail to insert.
     */
    public function generateUniqueSubdomain(string $companyName): string
    {
        $base = Str::slug($companyName);
        if ($base === '') {
            $base = 'empresa';
        }
        // Leave room for "-<n>" while staying within the 63-char label
        // limit the admin form's own validation regex enforces.
        $base = substr($base, 0, 55);
        if (strlen($base) < 3) {
            $base .= '-app';
        }

        $candidate = $base;
        $suffix    = 1;
        while (
            in_array($candidate, self::RESERVED_SUBDOMAINS, true)
            || Domain::where('domain', $candidate . '.fakturalista.com')->exists()
        ) {
            $suffix++;
            $candidate = $base . '-' . $suffix;
        }

        return $candidate;
    }

    /**
     * Provision a new tenant end-to-end:
     *
     *   1. Create the Tenant record (central DB).
     *      Stancl's TenancyServiceProvider listens to TenantCreated and runs
     *      Jobs\CreateDatabase + Jobs\MigrateDatabase synchronously.
     *      Jobs\CreateDatabase issues "CREATE DATABASE …" - a MySQL DDL statement
     *      that causes an implicit commit of any open transaction. Wrapping this
     *      in DB::transaction() therefore always throws "There is no active
     *      transaction" when the transaction layer tries to commit afterwards.
     *      Solution: no transaction wrapper here; cleanup is handled imperatively.
     *
     *   2. Create the Domain record (central DB).
     *
     *   3. Create the first admin User (tenant DB).
     *
     * Rollback strategy on failure:
     *   - $tenant->delete() fires TenantDeleted → Jobs\DeleteDatabase automatically
     *     drops the tenant database (if it was already created by the pipeline).
     *   - Domain records cascade-delete with the tenant record.
     *
     * @param array $data Required: company_name, company_email, owner_name,
     *        owner_email, admin_password, subdomain. Optional: company_phone,
     *        country, plan_slug.
     * @param bool $selfService When true (self-service trial registration,
     *        see RegisterTrialController), the welcome email never mentions
     *        a password - the visitor chose their own during registration,
     *        so there is nothing to hand back to them. When false (default -
     *        the existing Filament admin "create tenant" wizard), behavior
     *        is completely unchanged: the admin-generated/entered password
     *        is emailed once, exactly as before this parameter existed.
     */
    public function provision(array $data, bool $selfService = false): Tenant
    {
        $defaults = TenantContextService::defaultsForCountry($data['country'] ?? TenantContextService::DEFAULT_COUNTRY);
        $data += [
            'country' => $defaults['country'], 'currency' => $defaults['currency'],
            'language' => $defaults['locale'], 'timezone' => $defaults['timezone'],
        ];
        $tenant = null;
        $stage  = 'defaults_resolved';

        try {
            // ── Step 1: Tenant record ─────────────────────────────────────────
            // Stancl's JobPipeline listener (CreateDatabase + MigrateDatabase)
            // fires synchronously here. CREATE DATABASE is DDL - it implicitly
            // commits any open MySQL transaction, so no DB::transaction() wrapper.
            $tenant = Tenant::create([
                'company_name'        => $data['company_name'],
                'company_email'       => $data['company_email'],
                'company_phone'       => $data['company_phone'] ?? null,
                'country'             => $data['country'],
                'timezone'            => $data['timezone'],
                'currency'            => $data['currency'],
                'language'            => $data['language'],
                'status'              => 'active',
                'owner_name'          => $data['owner_name'],
                'owner_email'         => $data['owner_email'],
                'subscription_status' => 'trialing',
                'trial_ends_at'       => now()->addDays(config('billing.trial_days')),
            ]);
            $stage = 'tenant_created';

            // ── Step 2: Domain record ─────────────────────────────────────────
            $tenant->domains()->create([
                'domain' => $data['subdomain'] . '.fakturalista.com',
            ]);
            $stage = 'domain_created';

            // ── Step 3: Admin user (tenant DB) ────────────────────────────────
            // By this point the pipeline has already created and migrated the
            // tenant DB, so we can initialize tenancy and write the first user.
            tenancy()->initialize($tenant);
            try {
                User::create([
                    'name'     => $data['owner_name'],
                    'email'    => $data['owner_email'],
                    'password' => Hash::make($data['admin_password']),
                    // Without this, the admin's own UI locale falls back to
                    // the users.locale schema default ('es') regardless of
                    // the tenant's actual country/language - a Moroccan
                    // tenant's very first login would otherwise render in
                    // Spanish. See docs/morocco-phase-1a-implementation.md.
                    'locale'   => $data['language'],
                ]);
            } finally {
                try {
                    tenancy()->end();
                } catch (\Throwable $ex) {
                    Log::warning('tenancy()->end() failed after user creation', [
                        'error' => $ex->getMessage(),
                    ]);
                }
            }
            $stage = 'owner_user_created';

            // ── Step 4: Trial subscription row ───────────────────────────────
            // PlanService queries the subscriptions table (not tenant.subscription_status)
            // to resolve plan limits. Without this row, every limit check returns 0
            // (blocked) even during an active trial.
            $trialPlan = Plan::on('mysql')->where('slug', $data['plan_slug'] ?? 'starter')->first()
                      ?? Plan::on('mysql')->orderBy('sort_order')->first();

            if ($trialPlan) {
                Subscription::create([
                    'tenant_id'              => $tenant->getTenantKey(),
                    'plan_id'                => $trialPlan->id,
                    'provider'               => 'stripe',
                    'status'                 => 'trialing',
                    'trial_ends_at'          => $tenant->trial_ends_at,
                    'current_period_ends_at' => $tenant->trial_ends_at,
                ]);
            }
            $stage = 'trial_subscription_created';

            // ── Step 5: Welcome email ─────────────────────────────────────────
            // Isolated: a mail failure must never roll back a successfully
            // provisioned tenant (see the outer catch below - $tenant is
            // already returned to the caller regardless of what happens here).
            try {
                $loginUrl = 'https://' . $data['subdomain'] . '.fakturalista.com/admin/login';

                // The bug this fixes: the welcome email used to render in
                // whatever locale happened to be globally active (often
                // Spanish - see users.locale's schema default), completely
                // ignoring the tenant this email is actually about. $data
                // ['language'] is already resolved by this point (self-
                // service: TenantContextService::defaultsForCountry() from
                // the chosen country, e.g. 'fr' for Morocco; admin: the
                // Filament wizard's own explicit language field) - the
                // exact "tenant/company preference" this app's locale
                // priority is supposed to use when no more specific
                // explicit preference exists. ->locale() is Laravel's own
                // mechanism for this: it sets the translator locale right
                // before the mailable is built, for both a synchronous
                // send and a truly queued one (the locale travels with the
                // job), then restores it afterward - see WelcomeSelfServiceMail/
                // WelcomeTenantMail::envelope() for the __() calls this enables.
                if ($selfService) {
                    // No password field on this mailable at all - see its
                    // own class docblock for why.
                    Mail::send((new WelcomeSelfServiceMail(
                        tenant:     $tenant,
                        ownerEmail: $data['owner_email'],
                        ownerName:  $data['owner_name'],
                        loginUrl:   $loginUrl,
                    ))->locale($data['language']));
                } else {
                    // Admin-created-tenant path: only the locale resolution
                    // changed, everything else is unchanged. The admin
                    // chose/generated this password on the visitor's
                    // behalf, so it is handed back once here. Plain
                    // password lives only in memory ($data['admin_password'])
                    // - never persisted.
                    Mail::send((new WelcomeTenantMail(
                        tenant:        $tenant,
                        adminEmail:    $data['owner_email'],
                        adminName:     $data['owner_name'],
                        plainPassword: $data['admin_password'],
                        loginUrl:      $loginUrl,
                    ))->locale($data['language']));
                }
            } catch (\Throwable $mailEx) {
                Log::error('Welcome email failed after tenant provisioning', [
                    'tenant_id'    => $tenant->getTenantKey(),
                    'self_service' => $selfService,
                    'error'        => $mailEx->getMessage(),
                ]);
            }

            // ── Step 6: Admin new-registration notification ───────────────────
            // Self-service only: an admin using the Filament wizard already
            // knows about the tenant they just created, so this would be
            // pure noise on that path. Isolated in its own try/catch - kept
            // separate from the welcome-email one above - so a failure here
            // never masks (or is masked by) a welcome-email failure, and
            // never affects the already-provisioned $tenant this method
            // returns below regardless of what happens in either block.
            if ($selfService) {
                try {
                    Mail::send(new AdminNewRegistrationMail(
                        tenant:     $tenant,
                        ownerName:  $data['owner_name'],
                        ownerEmail: $data['owner_email'],
                        phone:      $data['company_phone'] ?? null,
                    ));
                } catch (\Throwable $notifyEx) {
                    Log::error('Admin new-registration notification failed', [
                        'tenant_id' => $tenant->getTenantKey(),
                        'error'     => $notifyEx->getMessage(),
                    ]);
                }
            }

            return $tenant;

        } catch (\Throwable $e) {
            // Never log $data itself - it may still contain the plaintext
            // password (self-service and admin paths alike) and, for the
            // admin path, admin_password_confirmation too.
            Log::error('Tenant provisioning failed', [
                'stage'        => $stage,
                'tenant_id'    => $tenant?->getTenantKey(),
                'self_service' => $selfService,
                'owner_email'  => $data['owner_email'] ?? null,
                'subdomain'    => $data['subdomain'] ?? null,
                'error'        => $e->getMessage(),
            ]);

            // If the Tenant record was persisted before the failure, delete it.
            // TenantDeleted event fires Jobs\DeleteDatabase automatically,
            // so the tenant database (if created) is also cleaned up.
            if ($tenant?->exists) {
                try {
                    $tenant->delete();
                } catch (\Throwable $cleanupEx) {
                    Log::warning('Rollback: could not delete tenant record', [
                        'tenant_id' => $tenant->getTenantKey(),
                        'error'     => $cleanupEx->getMessage(),
                    ]);
                }
            }

            throw new \RuntimeException(
                'Error al provisionar el tenant: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
