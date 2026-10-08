<?php

namespace Tests\Feature\Feedback;

use App\Mail\NewFeedbackAdminMail;
use App\Models\CompanyProfile;
use App\Models\Feedback;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tenant-app feedback widget: POST /api/feedback on a tenant domain
 * (FeedbackController::storeTenant, auth:api). Rows land in the CENTRAL
 * database, attributed from tenancy context + the authenticated user.
 * Real tenant databases, same pattern as UserManagementTest.
 */
class TenantFeedbackTest extends TestCase
{
    private array $tenants = [];
    private int $maxId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->maxId = (int) Feedback::max('id');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        Feedback::where('id', '>', $this->maxId)->get()->each->delete();
        foreach ($this->tenants as $tenant) {
            $tenant->delete();
        }
        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: string, 2: User} */
    private function makeTenant(string $company): array
    {
        $tenant = Tenant::create(['id' => 'test-fb-' . uniqid()]);
        $domain = $tenant->getTenantKey() . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);
        $tenant->update(['company_name' => $company, 'owner_email' => 'owner-' . $tenant->getTenantKey() . '@example.com']);
        $this->tenants[] = $tenant;

        tenancy()->initialize($tenant);
        $user = User::factory()->create([
            'name'   => 'Owner ' . $company,
            'email'  => 'owner-' . $tenant->getTenantKey() . '@example.com',
            'role'   => 'admin',
            'locale' => 'ar',
        ]);
        CompanyProfile::create(['legal_name' => $company, 'onboarding_completed_at' => now()]);
        tenancy()->end();

        return [$tenant, $domain, $user];
    }

    private function url(string $domain): string
    {
        return 'http://' . $domain . '/api/feedback';
    }

    private function created(): ?Feedback
    {
        return Feedback::where('id', '>', $this->maxId)->latest('id')->first();
    }

    public function test_an_authenticated_tenant_user_submission_is_attributed_server_side(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('Atlas SARL');

        $this->actingAs($user, 'api')
            ->postJson($this->url($domain), [
                'type'      => 'bug',
                'message'   => "L'export Excel des factures est vide.",
                'page_url'  => 'http://' . $domain . '/admin/invoices?search=x',
                // Spoof attempts - all ignored:
                'email'     => 'spoof@evil.example',
                'tenant_id' => 'another-tenant',
                'user_id'   => 999999,
                'source'    => 'public',
            ])
            ->assertCreated();

        $feedback = $this->created();
        $this->assertNotNull($feedback, 'Stored in the central database.');
        $this->assertSame('mysql', $feedback->getConnectionName());
        $this->assertSame('tenant', $feedback->source);
        $this->assertSame($tenant->getTenantKey(), $feedback->tenant_id);
        $this->assertSame($user->id, $feedback->user_id);
        $this->assertSame($user->email, $feedback->email);
        $this->assertSame('Atlas SARL', $feedback->company_name);
        $this->assertSame($user->name, $feedback->user_name);
        $this->assertSame('ar', $feedback->locale);
        $this->assertSame('http://' . $domain . '/admin/invoices', $feedback->page_url);

        Mail::assertSent(NewFeedbackAdminMail::class, function (NewFeedbackAdminMail $mail) use ($feedback) {
            $html = $mail->render();

            // Link points at the central backoffice, not the tenant subdomain.
            return str_contains($html, rtrim(config('app.url'), '/') . '/backoffice/feedback/' . $feedback->id)
                && str_contains($html, 'Atlas SARL');
        });
    }

    public function test_the_tenant_endpoint_requires_authentication(): void
    {
        [, $domain] = $this->makeTenant('Anon Co');

        $this->postJson($this->url($domain), ['type' => 'bug', 'message' => 'Sans connexion.'])
            ->assertUnauthorized();

        $this->assertNull($this->created());
    }

    public function test_each_tenant_submission_is_attributed_to_the_tenant_that_served_it(): void
    {
        [$tenantA, $domainA, $userA] = $this->makeTenant('Alpha');
        [$tenantB, $domainB, $userB] = $this->makeTenant('Beta');

        $this->actingAs($userA, 'api')
            ->postJson($this->url($domainA), ['type' => 'suggestion', 'message' => 'Retour depuis Alpha.'])
            ->assertCreated();
        $this->assertSame($tenantA->getTenantKey(), $this->created()->tenant_id);

        $this->app['auth']->forgetGuards();

        $this->actingAs($userB, 'api')
            ->postJson($this->url($domainB), ['type' => 'improvement', 'message' => 'Retour depuis Beta.', 'tenant_id' => $tenantA->getTenantKey()])
            ->assertCreated();

        $latest = $this->created();
        $this->assertSame($tenantB->getTenantKey(), $latest->tenant_id);
        $this->assertSame('Beta', $latest->company_name);
        $this->assertSame(1, Feedback::where('id', '>', $this->maxId)->where('tenant_id', $tenantA->getTenantKey())->count());

        // Tenants never get a read endpoint for feedback.
        $this->actingAs($userB, 'api')->getJson($this->url($domainB))->assertStatus(405);
    }

    public function test_tenant_validation_and_screenshot_storage_stay_central(): void
    {
        Storage::fake(Feedback::DISK);
        [, $domain, $user] = $this->makeTenant('Gamma');

        $this->actingAs($user, 'api')
            ->postJson($this->url($domain), ['type' => 'bug', 'message' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message'])
            ->assertJsonMissingValidationErrors(['email']);

        $this->actingAs($user, 'api')
            ->post($this->url($domain), [
                'type'       => 'bug',
                'message'    => 'Capture du problème jointe.',
                'screenshot' => UploadedFile::fake()->image('bug.jpg', 1200, 800),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $feedback = $this->created();
        $this->assertSame('image/jpeg', $feedback->attachment_mime);
        Storage::disk(Feedback::DISK)->assertExists($feedback->attachment_path);
    }

    public function test_the_feedback_disk_is_not_tenant_suffixed(): void
    {
        [$tenant] = $this->makeTenant('Delta');
        $central = config('filesystems.disks.feedback.root');

        tenancy()->initialize($tenant);
        $root = Storage::disk(Feedback::DISK)->path('');
        tenancy()->end();

        $this->assertSame(rtrim($central, '/'), rtrim($root, '/'));
        $this->assertStringNotContainsString($tenant->getTenantKey(), $root);
    }

    public function test_the_tenant_endpoint_is_rate_limited_per_user(): void
    {
        [, $domain, $user] = $this->makeTenant('Epsilon');

        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($user, 'api')
                ->postJson($this->url($domain), ['type' => 'other', 'message' => "Message numéro $i."])
                ->assertCreated();
        }

        $this->actingAs($user, 'api')
            ->postJson($this->url($domain), ['type' => 'other', 'message' => 'Un de trop.'])
            ->assertStatus(429);
    }
}
