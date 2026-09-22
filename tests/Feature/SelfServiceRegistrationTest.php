<?php

namespace Tests\Feature;

use App\Mail\AdminNewRegistrationMail;
use App\Mail\WelcomeSelfServiceMail;
use App\Models\CompanyProfile;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Self-service free-trial signup (routes/web.php POST /register ->
 * RegisterTrialController -> TenantProvisioningService::provision(...,
 * selfService: true)) - the same provisioning pipeline the Filament admin
 * "create tenant" wizard already uses, per the task's explicit "do not
 * duplicate the provisioning logic" instruction.
 */
class SelfServiceRegistrationTest extends TestCase
{
    private array $createdTenantIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdTenantIds as $id) {
            Tenant::find($id)?->delete();
        }
        parent::tearDown();
    }

    private function uniqueEmail(string $label = 'owner'): string
    {
        return $label . '+' . uniqid() . '@example.com';
    }

    /**
     * Performs a GET first to seed a real math-captcha challenge into the
     * (array-driver, per phpunit.xml) test session, then returns a valid
     * registration payload whose captcha_answer will pass
     * MathCaptchaService::verify() - it stores the expected sum directly
     * under this session key (see app/Services/MathCaptchaService.php).
     */
    private function payloadWithFreshCaptcha(array $overrides = []): array
    {
        $this->get('http://fakturalista.test/register');
        $captchaAnswer = session('math_captcha_answer');

        return array_merge([
            'name'            => 'Maria Garcia',
            'email'           => $this->uniqueEmail(),
            'password'        => 'SecurePass123',
            'captcha_answer'  => $captchaAnswer,
        ], $overrides);
    }

    private function registeredTenant(string $email): Tenant
    {
        $tenant = Tenant::where('owner_email', $email)->firstOrFail();
        $this->createdTenantIds[] = $tenant->getTenantKey();

        return $tenant;
    }

    // ── 1/2. Successful registration + tenant fields ───────────────

    /** @test */
    public function user_can_register_successfully_and_tenant_is_created_with_correct_trial(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha(['email' => $email]);

        $before = now();
        $response = $this->post('http://fakturalista.test/register', $payload);
        $after  = now();

        $tenant = $this->registeredTenant($email);
        $domain = $tenant->domains->first()->domain;

        $response->assertRedirect('https://' . $domain . '/admin/login?welcome=1&email=' . urlencode($email));

        // No business name is collected at signup - the owner's own name
        // is used as a placeholder until onboarding sets the real one.
        $this->assertSame('Maria Garcia', $tenant->company_name);
        $this->assertSame('Maria Garcia', $tenant->owner_name);
        $this->assertSame($email, $tenant->owner_email);
        $this->assertSame('active', $tenant->status);
        $this->assertSame('trialing', $tenant->subscription_status);
        $this->assertStringStartsWith('maria-garcia', $domain);
        $this->assertStringEndsWith('.fakturalista.com', $domain);

        // 10. Trial ends exactly 14 days later (config('billing.trial_days')).
        $this->assertGreaterThanOrEqual($before->copy()->addDays(14)->timestamp, $tenant->trial_ends_at->timestamp);
        $this->assertLessThanOrEqual($after->copy()->addDays(14)->timestamp, $tenant->trial_ends_at->timestamp);
    }

    // ── Phone is optional ────────────────────────────────────────────

    /** @test */
    public function registration_succeeds_without_a_phone_number(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha(['email' => $email]);
        unset($payload['phone']);

        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        $tenant = $this->registeredTenant($email);
        $this->assertNull($tenant->company_phone);
    }

    /** @test */
    public function a_provided_phone_number_is_stored_on_the_tenant(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha(['email' => $email, 'phone' => '+212 6XX XXX XXX']);

        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        $tenant = $this->registeredTenant($email);
        $this->assertSame('+212 6XX XXX XXX', $tenant->company_phone);
    }

    // ── 3/4/5. DB provisioning, migrations, domain, seeded countries ──

    /** @test */
    public function registration_provisions_tenant_database_runs_migrations_creates_domain_and_seeds_countries(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha(['email' => $email]);
        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        $tenant = $this->registeredTenant($email);

        $this->assertCount(1, $tenant->domains);
        $this->assertStringEndsWith('.fakturalista.com', $tenant->domains->first()->domain);

        $tenant->run(function () {
            // The tenant migrations ran (Stancl's JobPipeline: CreateDatabase
            // -> MigrateDatabase) - spot-check a couple of core tables exist.
            $this->assertTrue(Schema::hasTable('users'));
            $this->assertTrue(Schema::hasTable('customers'));
            $this->assertTrue(Schema::hasTable('countries'));

            // SeedTenantCountries ran (Stancl's JobPipeline third step).
            $this->assertGreaterThan(0, Country::count());
        });
    }

    // ── 6/8. Owner user in tenant DB, correct connection, hashed password ──

    /** @test */
    public function owner_user_is_created_in_the_tenant_database_with_a_hashed_password(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha([
            'email' => $email, 'password' => 'MyRealPassword1',
        ]);
        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        $tenant = $this->registeredTenant($email);

        // The central database has no `users` table at all - Users only
        // ever exist per-tenant (see TenantProvisioningService::provision()
        // step 3) - confirmed structurally, not just by this test's own
        // positive assertion below.
        $this->assertFalse(
            Schema::connection('mysql')->hasTable('users'),
            'Users must never live in the central database.'
        );

        $tenant->run(function () use ($email) {
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user);
            $this->assertNotSame('MyRealPassword1', $user->password, 'Password must never be stored in plain text.');
            $this->assertTrue(Hash::check('MyRealPassword1', $user->password));
        });
    }

    // ── 9. Trial subscription row created (PlanService dependency) ──

    /** @test */
    public function registration_creates_a_trial_subscription_row_on_the_starter_plan(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha(['email' => $email]);
        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        $tenant = $this->registeredTenant($email);

        $subscription = Subscription::where('tenant_id', $tenant->getTenantKey())->first();
        $this->assertNotNull($subscription);
        $this->assertSame('trialing', $subscription->status);
        $this->assertNotNull($subscription->plan_id);
        $this->assertEquals($tenant->trial_ends_at->timestamp, $subscription->trial_ends_at->timestamp);
    }

    // ── 11. Duplicate email is rejected ────────────────────────────

    /** @test */
    public function duplicate_email_is_rejected_and_no_second_tenant_is_created(): void
    {
        Mail::fake();
        $email = $this->uniqueEmail();

        $first = $this->post('http://fakturalista.test/register', $this->payloadWithFreshCaptcha(['email' => $email]));
        $first->assertRedirect();
        $this->registeredTenant($email);

        $second = $this->post('http://fakturalista.test/register', $this->payloadWithFreshCaptcha([
            'email' => $email, 'name' => 'A Totally Different Person',
        ]));
        $second->assertSessionHasErrors('email');

        $this->assertSame(1, Tenant::where('owner_email', $email)->count());
    }

    // ── 12. Duplicate/concurrent submission does not create two tenants ──

    /** @test */
    public function a_registration_already_in_flight_for_the_same_email_is_rejected_instead_of_double_provisioning(): void
    {
        $email = $this->uniqueEmail();

        // Simulates a second concurrent request arriving while the first
        // is still inside TenantProvisioningService::provision() - the
        // exact scenario a double-click/browser-retry produces. See
        // RegisterTrialController::store()'s Cache::lock().
        $lock = Cache::lock('register-trial:' . $email, 30);
        $this->assertTrue($lock->get());

        try {
            $response = $this->post('http://fakturalista.test/register', $this->payloadWithFreshCaptcha(['email' => $email]));
            $response->assertSessionHasErrors('email');
            $this->assertSame(0, Tenant::where('owner_email', $email)->count());
        } finally {
            $lock->release();
        }
    }

    // ── 13. Provisioning failure is handled safely (no orphan tenant) ──

    /** @test */
    public function provisioning_failure_leaves_no_orphan_tenant_or_domain(): void
    {
        $service = app(TenantProvisioningService::class);
        $email   = $this->uniqueEmail();

        // Force the exact failure mode TenantProvisioningService's own
        // docblock is written around: a domain collision after the Tenant
        // row already exists. Pre-create the domain the pipeline is about
        // to try to claim, bypassing generateUniqueSubdomain() on purpose.
        $existingTenant = Tenant::create([
            'company_name' => 'Existing Co', 'company_email' => 'existing@example.com',
            'owner_name' => 'Existing Owner', 'owner_email' => $this->uniqueEmail('existing'),
            'status' => 'active', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(14),
        ]);
        $existingTenant->domains()->create(['domain' => 'taken-slug.fakturalista.com']);
        $this->createdTenantIds[] = $existingTenant->getTenantKey();

        $tenantCountBefore = Tenant::count();

        $threw = false;
        try {
            $service->provision([
                'company_name'   => 'Whatever Inc',
                'company_email'  => $email,
                'owner_name'     => 'Test Owner',
                'owner_email'    => $email,
                'admin_password' => 'SomePassword1',
                'subdomain'      => 'taken-slug', // guaranteed collision
                'plan_slug'      => 'starter',
            ], selfService: true);
        } catch (\RuntimeException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'provision() must throw, not silently return a broken tenant.');
        $this->assertSame($tenantCountBefore, Tenant::count(), 'A failed provision() must not leave an orphan Tenant row.');
        $this->assertSame(0, Tenant::where('owner_email', $email)->count());
    }

    // ── 16. Welcome email dispatched, never contains the password ──

    /** @test */
    public function welcome_email_is_dispatched_and_never_contains_the_chosen_password(): void
    {
        Mail::fake();
        $email    = $this->uniqueEmail();
        $password = 'SuperSecretPass99';
        $payload  = $this->payloadWithFreshCaptcha(['email' => $email, 'password' => $password]);
        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        // WelcomeSelfServiceMail implements ShouldQueue, so under Mail::fake()
        // a Mail::send() call is recorded as queued, not sent (it would only
        // become an immediate "sent" if dispatched via a sync queue worker) -
        // see WelcomeTenantMail's own docblock for the same behavior noted
        // on the admin-created-tenant path.
        Mail::assertQueued(WelcomeSelfServiceMail::class, function (WelcomeSelfServiceMail $mail) use ($email, $password) {
            if ($mail->ownerEmail !== $email) {
                return false;
            }
            $html = $mail->render();
            $this->assertStringNotContainsString($password, $html);
            $this->assertStringContainsString('/admin/login', $html);
            // Morocco is the default market (no country field is collected
            // at signup any more - see RegisterTrialRequest) - the welcome
            // email must render in French, not the 'es' schema default.
            $this->assertStringContainsString('Bienvenue sur Fakturalista', $html);
            return true;
        });
    }

    // ── 17. Email failure does not destroy a successfully created tenant ──

    /** @test */
    public function a_welcome_email_failure_does_not_roll_back_a_successfully_provisioned_tenant(): void
    {
        // Both the welcome email and the admin notification go through
        // Mail::send() (see TenantProvisioningService::provision()'s two
        // isolated try/catch blocks) - simulate both failing.
        Mail::shouldReceive('send')->twice()->andThrow(new \RuntimeException('SMTP connection refused'));

        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha(['email' => $email]);

        // The request must still succeed end-to-end from the visitor's
        // perspective - TenantProvisioningService isolates the mail step
        // in its own try/catch specifically so this never surfaces as a
        // registration failure.
        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        $tenant = $this->registeredTenant($email);
        $this->assertSame('trialing', $tenant->subscription_status);
        $tenant->run(fn () => $this->assertNotNull(User::where('email', $email)->first()));
    }

    // ── Admin new-registration notification ─────────────────────────

    /** @test */
    public function admin_notification_is_sent_after_a_successful_registration_with_correct_details_and_no_password(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha([
            'email' => $email, 'phone' => '+212 6XX XXX XXX', 'password' => 'SecretPass123',
        ]);

        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();

        $tenant = $this->registeredTenant($email);

        Mail::assertQueued(AdminNewRegistrationMail::class, function (AdminNewRegistrationMail $mail) use ($tenant, $email) {
            if ($mail->tenant->getTenantKey() !== $tenant->getTenantKey()) {
                return false;
            }
            $this->assertSame(config('fakturalista.admin_email'), $mail->envelope()->to[0]->address);
            $this->assertSame('Nouvelle inscription sur Fakturalista 🎉', $mail->envelope()->subject);
            $this->assertSame('Maria Garcia', $mail->ownerName);
            $this->assertSame($email, $mail->ownerEmail);
            $this->assertSame('+212 6XX XXX XXX', $mail->phone);

            $html = $mail->render();
            $this->assertStringContainsString('Maria Garcia', $html);
            $this->assertStringContainsString($email, $html);
            $this->assertStringContainsString('+212 6XX XXX XXX', $html);
            $this->assertStringNotContainsString('SecretPass123', $html);

            return true;
        });
    }

    /** @test */
    public function admin_notification_shows_non_renseigne_when_no_phone_was_given(): void
    {
        Mail::fake();
        $email   = $this->uniqueEmail();
        $payload = $this->payloadWithFreshCaptcha(['email' => $email]);
        unset($payload['phone']);

        $this->post('http://fakturalista.test/register', $payload)->assertRedirect();
        $this->registeredTenant($email);

        Mail::assertQueued(AdminNewRegistrationMail::class, function (AdminNewRegistrationMail $mail) use ($email) {
            if ($mail->ownerEmail !== $email) {
                return false;
            }
            $this->assertNull($mail->phone);
            $this->assertStringContainsString('Non renseigné', $mail->render());
            return true;
        });
    }

    /** @test */
    public function admin_notification_is_never_sent_for_a_failed_registration(): void
    {
        Mail::fake();
        $email = $this->uniqueEmail();

        // Same concurrency-lock scenario as the "already in flight" test -
        // the request is rejected before any provisioning happens.
        $lock = Cache::lock('register-trial:' . $email, 30);
        $this->assertTrue($lock->get());

        try {
            $this->post('http://fakturalista.test/register', $this->payloadWithFreshCaptcha(['email' => $email]))
                ->assertSessionHasErrors('email');
        } finally {
            $lock->release();
        }

        Mail::assertNotQueued(AdminNewRegistrationMail::class);
    }

    /** @test */
    public function admin_notification_is_not_sent_for_admin_created_tenants(): void
    {
        Mail::fake();
        $service = app(TenantProvisioningService::class);
        $email   = $this->uniqueEmail();

        $tenant = $service->provision([
            'company_name'   => 'Filament Created Co',
            'company_email'  => $email,
            'owner_name'     => 'Filament Admin',
            'owner_email'    => $email,
            'admin_password' => 'SomePassword1',
            'subdomain'      => 'filament-created-' . uniqid(),
            'plan_slug'      => 'starter',
        ], selfService: false);
        $this->createdTenantIds[] = $tenant->getTenantKey();

        Mail::assertNotQueued(AdminNewRegistrationMail::class);
    }

    // ── 18. Tenant isolation is preserved ──────────────────────────

    /** @test */
    public function tenant_isolation_is_preserved_between_two_self_service_signups(): void
    {
        Mail::fake();
        $emailA = $this->uniqueEmail('a');
        $emailB = $this->uniqueEmail('b');

        $this->post('http://fakturalista.test/register', $this->payloadWithFreshCaptcha(['email' => $emailA, 'name' => 'Tenant A Owner']))->assertRedirect();
        $tenantA = $this->registeredTenant($emailA);

        $this->post('http://fakturalista.test/register', $this->payloadWithFreshCaptcha(['email' => $emailB, 'name' => 'Tenant B Owner']))->assertRedirect();
        $tenantB = $this->registeredTenant($emailB);

        $tenantA->run(function () {
            CompanyProfile::create(['legal_name' => 'Tenant A Legal Name', 'country_code' => 'MA']);
            Customer::factory()->create(['company_name' => 'A-only customer']);
        });

        $tenantB->run(function () {
            // Tenant B's own DB must start clean - no company profile, no
            // customer, and certainly not tenant A's data.
            $this->assertNull(CompanyProfile::first());
            $this->assertSame(0, Customer::where('company_name', 'A-only customer')->count());
            // Its own owner user must exist and be the ONLY user.
            $this->assertSame(1, User::count());
        });

        $tenantA->run(function () use ($emailB) {
            $this->assertNull(User::where('email', $emailB)->first());
        });
    }

    // ── Subdomain generation / collision handling ──────────────────

    /** @test */
    public function generated_subdomains_normalize_the_business_name_and_resolve_collisions_deterministically(): void
    {
        $service = app(TenantProvisioningService::class);

        $slug = $service->generateUniqueSubdomain('Café Déjà-Vu & Co. S.L.');
        $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $slug);

        $collisionTenant = Tenant::create([
            'company_name' => 'Collision Test', 'company_email' => 'collision@example.com',
            'owner_name' => 'X', 'owner_email' => $this->uniqueEmail('collision'),
            'status' => 'active', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(14),
        ]);
        $collisionTenant->domains()->create(['domain' => $slug . '.fakturalista.com']);
        $this->createdTenantIds[] = $collisionTenant->getTenantKey();

        $secondSlug = $service->generateUniqueSubdomain('Café Déjà-Vu & Co. S.L.');
        $this->assertNotSame($slug, $secondSlug, 'A colliding business name must resolve to a different, unique subdomain.');
        $this->assertStringStartsWith($slug, $secondSlug);
    }

    /** @test */
    public function reserved_subdomain_words_are_never_auto_assigned(): void
    {
        $service = app(TenantProvisioningService::class);
        $slug    = $service->generateUniqueSubdomain('www');
        $this->assertNotSame('www', $slug);
    }

    // ── 14/15. Trial expiration + active-subscription bypass ───────
    //
    // These exercise the centralized trial-status API this feature relies
    // on (Tenant::canAccessApp()/EnforceSubscription middleware, both
    // pre-existing) to prove the trial_ends_at this registration flow sets
    // actually produces the right access behavior end-to-end. Neither had
    // any test coverage before this file.

    private function onboardedTenantDomain(array $tenantOverrides): array
    {
        $tenant = Tenant::create(array_merge([
            'company_name' => 'Trial Status Co', 'company_email' => 'trialstatus@example.com',
            'owner_name' => 'Trial Owner', 'owner_email' => $this->uniqueEmail('trialstatus'),
            'status' => 'active',
        ], $tenantOverrides));
        $this->createdTenantIds[] = $tenant->getTenantKey();

        $domain = $tenant->id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        // PlanService resolves limits from the `subscriptions` table, not
        // tenant.subscription_status directly (see
        // TenantProvisioningService::provision() step 4's own comment) -
        // without this row every plan-limit check treats the tenant as
        // having no plan at all, regardless of trial/active status.
        $plan = Plan::on('mysql')->orderBy('sort_order')->first();
        if ($plan) {
            Subscription::create([
                'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $plan->id,
                'provider' => 'stripe', 'status' => $tenant->subscription_status,
                'trial_ends_at' => $tenant->trial_ends_at, 'current_period_ends_at' => $tenant->trial_ends_at,
            ]);
        }

        $user = $tenant->run(function () {
            CompanyProfile::create(['legal_name' => 'Trial Status Co', 'country_code' => 'MA', 'onboarding_completed_at' => now()]);
            return User::factory()->create();
        });

        return [$tenant, $domain, $user];
    }

    /** @test */
    public function an_expired_trial_blocks_write_requests_but_still_allows_reads_and_login(): void
    {
        [$tenant, $domain, $user] = $this->onboardedTenantDomain([
            'subscription_status' => 'trialing',
            'trial_ends_at'       => now()->subDays(1), // expired yesterday
        ]);

        $this->actingAs($user, 'api');

        $write = $this->postJson('http://' . $domain . '/api/customers', [
            'type' => 1, 'company_name' => 'Should Be Blocked', 'contacts' => [],
        ]);
        $write->assertStatus(402)->assertJsonPath('error', 'subscription_required');

        // Reads and account-level actions must still work - no lockout,
        // no redirect loop (the brief's explicit requirement).
        $this->getJson('http://' . $domain . '/api/user')->assertOk();
    }

    /** @test */
    public function an_active_paid_subscription_bypasses_the_trial_restriction(): void
    {
        [$tenant, $domain, $user] = $this->onboardedTenantDomain([
            'subscription_status' => 'active',
            'trial_ends_at'       => now()->subDays(30), // long expired, irrelevant once active
        ]);

        $this->actingAs($user, 'api');

        $write = $this->postJson('http://' . $domain . '/api/customers', [
            'type' => 1, 'company_name' => 'Should Be Allowed', 'contacts' => [],
        ]);
        $write->assertStatus(200);
    }
}
