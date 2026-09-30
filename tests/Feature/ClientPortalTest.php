<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClientPortal\ClientPortalService;
use App\Services\EInvoicing\Ubl\UblValidationError;
use App\Services\EInvoicing\Ubl\UblValidationResult;
use App\Services\EInvoicing\Ubl\UblValidator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Client Portal, Step 1 (backend foundation only - see the Step 1
 * report). No Vue page exists yet; this only covers
 * App\Services\ClientPortal\ClientPortalService and
 * GET /portal/{token} (App\Http\Controllers\ClientPortalController).
 *
 * Setup mirrors tests/Feature/EInvoiceMapperTest.php's makeTenant()/
 * tenant->run() pattern.
 */
class ClientPortalTest extends TestCase
{
    private function makeTenant(array $companyOverrides = []): array
    {
        $id     = 'test-portal-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        User::factory()->create();
        CompanyProfile::create(array_merge([
            'legal_name'              => 'Test Co',
            'trade_name'              => 'Test Co Trade Name',
            'country_code'            => 'MA',
            'currency'                => 'MAD',
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ], $companyOverrides));
        tenancy()->end();

        return [$tenant, $domain];
    }

    private function apiUrl(string $domain, string $path): string
    {
        return 'http://' . $domain . $path;
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::factory()->create(array_merge(['type' => 1], $overrides));
    }

    private function makeInvoice(Customer $customer, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-PORTAL-' . uniqid(),
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_ISSUED,
            'sub_total'       => 100.00,
            'vta'             => 0,
            'vta4' => 0, 'vta10' => 0, 'vta21' => 0,
            'total'           => 100.00,
        ], $overrides));
    }

    private function makeQuote(Customer $customer, array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'QUO-PORTAL-' . uniqid(),
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Quote::STATUS_SENT,
            'sub_total'       => 50.00,
            'vta'             => 0,
            'total'           => 50.00,
        ], $overrides));
    }

    // ── Token generation / service-level behaviour ──────────────────

    /** @test */
    public function creating_access_issues_a_high_entropy_token_and_never_stores_it_raw(): void
    {
        [$tenant] = $this->makeTenant();

        $tenant->run(function () {
            $customer = $this->makeCustomer();
            $token    = app(ClientPortalService::class)->createAccess($customer);

            $this->assertSame(64, strlen($token));
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);

            $row = CustomerPortalAccess::where('customer_id', $customer->id)->first();
            $this->assertNotNull($row);
            $this->assertSame(64, strlen($row->token_hash));
            $this->assertNotSame($token, $row->token_hash);
            $this->assertSame(hash('sha256', $token), $row->token_hash);
        });

        $tenant->delete();
    }

    /** @test */
    public function two_generated_tokens_are_not_equal(): void
    {
        [$tenant] = $this->makeTenant();

        $tenant->run(function () {
            $customer = $this->makeCustomer();
            $service  = app(ClientPortalService::class);

            $tokenA = $service->createAccess($customer);
            $tokenB = $service->createAccess($customer);

            $this->assertNotSame($tokenA, $tokenB);
        });

        $tenant->delete();
    }

    // ── HTTP: valid / invalid / revoked access ──────────────────────

    /** @test */
    public function a_valid_token_returns_the_expected_safe_summary(): void
    {
        [$tenant, $domain] = $this->makeTenant(['trade_name' => 'Acme Trade']);

        $token = null;
        $tenant->run(function () use (&$token) {
            $customer = $this->makeCustomer(['company_name' => 'Client SARL']);
            $this->makeInvoice($customer);
            $this->makeQuote($customer);
            $token = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'company' => ['name', 'logo_url', 'brand_color'],
            'customer' => ['name'],
            'summary' => ['total_outstanding', 'overdue_amount', 'unpaid_invoices_count'],
            'invoices' => [['uuid', 'number', 'issue_date', 'due_date', 'total', 'status', 'currency']],
            'quotes' => [['uuid', 'number', 'date', 'total', 'status', 'currency']],
        ]);
        $this->assertSame('Acme Trade', $response->json('company.name'));
        $this->assertSame('Client SARL', $response->json('customer.name'));

        $tenant->delete();
    }

    /** @test */
    public function a_valid_access_updates_last_accessed_at(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $tenant->run(function () use (&$token) {
            $customer = $this->makeCustomer();
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $this->getJson($this->apiUrl($domain, '/api/portal/' . $token))->assertStatus(200);

        $tenant->run(function () use ($token) {
            $row = CustomerPortalAccess::where('token_hash', hash('sha256', $token))->first();
            $this->assertNotNull($row->last_accessed_at);
        });

        $tenant->delete();
    }

    /** @test */
    public function an_invalid_token_returns_404_and_reveals_nothing(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        $tenant->run(fn () => $this->makeCustomer());

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . Str::random(64)));

        $response->assertStatus(404);
        $this->assertStringNotContainsString('customer', strtolower($response->getContent()));

        $tenant->delete();
    }

    /** @test */
    public function a_revoked_token_returns_410(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $tenant->run(function () use (&$token) {
            $customer = $this->makeCustomer();
            $service  = app(ClientPortalService::class);
            $token    = $service->createAccess($customer);
            $service->revoke($customer);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));

        $response->assertStatus(410);

        $tenant->delete();
    }

    /** @test */
    public function regenerating_access_invalidates_the_old_token_and_issues_a_working_new_one(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $oldToken = null;
        $newToken = null;
        $tenant->run(function () use (&$oldToken, &$newToken) {
            $customer  = $this->makeCustomer();
            $service   = app(ClientPortalService::class);
            $oldToken  = $service->createAccess($customer);
            $newToken  = $service->regenerateAccess($customer);
        });

        $this->getJson($this->apiUrl($domain, '/api/portal/' . $oldToken))->assertStatus(410);
        $this->getJson($this->apiUrl($domain, '/api/portal/' . $newToken))->assertStatus(200);

        $tenant->delete();
    }

    // ── Row-level scoping ────────────────────────────────────────────

    /** @test */
    public function a_customer_only_sees_their_own_invoices(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $tenant->run(function () use (&$token) {
            $customerA = $this->makeCustomer(['company_name' => 'Customer A']);
            $customerB = $this->makeCustomer(['company_name' => 'Customer B']);
            $this->makeInvoice($customerA, ['reference' => 'INV-A']);
            $this->makeInvoice($customerB, ['reference' => 'INV-B']);
            $token = app(ClientPortalService::class)->createAccess($customerA);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));

        $response->assertStatus(200);
        $numbers = collect($response->json('invoices'))->pluck('number')->all();
        $this->assertContains('INV-A', $numbers);
        $this->assertNotContains('INV-B', $numbers);

        $tenant->delete();
    }

    /** @test */
    public function a_customer_only_sees_their_own_quotes(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $tenant->run(function () use (&$token) {
            $customerA = $this->makeCustomer(['company_name' => 'Customer A']);
            $customerB = $this->makeCustomer(['company_name' => 'Customer B']);
            $this->makeQuote($customerA, ['reference' => 'QUO-A']);
            $this->makeQuote($customerB, ['reference' => 'QUO-B']);
            $token = app(ClientPortalService::class)->createAccess($customerA);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));

        $response->assertStatus(200);
        $numbers = collect($response->json('quotes'))->pluck('number')->all();
        $this->assertContains('QUO-A', $numbers);
        $this->assertNotContains('QUO-B', $numbers);

        $tenant->delete();
    }

    /** @test */
    public function draft_invoices_and_quotes_are_never_shown_in_the_portal(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $tenant->run(function () use (&$token) {
            $customer = $this->makeCustomer();
            $this->makeInvoice($customer, ['reference' => 'INV-DRAFT', 'status' => Invoice::STATUS_DRAFT]);
            $this->makeQuote($customer, ['reference' => 'QUO-DRAFT', 'status' => Quote::STATUS_DRAFT]);
            $token = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));

        $this->assertSame([], $response->json('invoices'));
        $this->assertSame([], $response->json('quotes'));

        $tenant->delete();
    }

    // ── Tenant isolation ─────────────────────────────────────────────

    /** @test */
    public function a_token_from_tenant_a_does_not_resolve_on_tenant_bs_domain(): void
    {
        [$tenantA] = $this->makeTenant();
        [$tenantB, $domainB] = $this->makeTenant();

        $tokenA = null;
        $tenantA->run(function () use (&$tokenA) {
            $customer = $this->makeCustomer();
            $tokenA   = app(ClientPortalService::class)->createAccess($customer);
        });

        // Tenant B has its own, completely separate database - tenant A's
        // token_hash simply doesn't exist there.
        $response = $this->getJson($this->apiUrl($domainB, '/api/portal/' . $tokenA));
        $response->assertStatus(404);

        $tenantA->delete();
        $tenantB->delete();
    }

    // ── No sensitive data exposed ─────────────────────────────────────

    /** @test */
    public function no_internal_ids_or_private_fields_are_exposed(): void
    {
        [$tenant, $domain] = $this->makeTenant([
            'trade_name' => 'Acme Trade',
            'tax_id'     => 'SECRET-TAX-ID',
        ]);

        $token = null;
        $tenant->run(function () use (&$token) {
            $customer = $this->makeCustomer([
                'company_name' => 'Client SARL',
                'email'        => 'client@example.com',
                'phone'        => '+212600000000',
                'tax_id'       => 'CLIENT-SECRET-TAX-ID',
            ]);
            $this->makeInvoice($customer, ['note' => 'Internal note - never expose']);
            $token = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));
        $response->assertStatus(200);

        $data = $response->json();

        // Top-level shape only contains the documented, safe keys.
        $this->assertEqualsCanonicalizing(['company', 'customer', 'summary', 'invoices', 'quotes'], array_keys($data));
        $this->assertEqualsCanonicalizing(['name', 'logo_url', 'brand_color'], array_keys($data['company']));
        $this->assertEqualsCanonicalizing(['name'], array_keys($data['customer']));
        foreach ($data['invoices'] as $invoice) {
            $this->assertEqualsCanonicalizing(
                // paid_at + payment: Step 6B (Pay now) - see ClientPortalStripePaymentTest.
                ['uuid', 'number', 'issue_date', 'due_date', 'total', 'status', 'currency', 'paid_at', 'payment'],
                array_keys($invoice)
            );
            $this->assertEqualsCanonicalizing(['payable', 'provider'], array_keys($invoice['payment']));
        }

        $raw = $response->getContent();
        $this->assertStringNotContainsString('SECRET-TAX-ID', $raw);
        $this->assertStringNotContainsString('CLIENT-SECRET-TAX-ID', $raw);
        $this->assertStringNotContainsString('client@example.com', $raw);
        $this->assertStringNotContainsString('+212600000000', $raw);
        $this->assertStringNotContainsString('Internal note', $raw);
        $this->assertStringNotContainsString('"id":', $raw);
        $this->assertStringNotContainsString('customer_id', $raw);

        $tenant->delete();
    }

    // ── Outstanding amount calculation ────────────────────────────────

    /** @test */
    public function outstanding_and_overdue_amounts_are_calculated_correctly(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $tenant->run(function () use (&$token) {
            $customer = $this->makeCustomer();

            // Issued, not yet due - counts toward outstanding, not overdue.
            $this->makeInvoice($customer, [
                'total' => 100.00, 'status' => Invoice::STATUS_ISSUED,
                'expiration_date' => now()->addDays(10)->toDateString(),
            ]);
            // Issued, past due date - counts toward both outstanding and overdue.
            $this->makeInvoice($customer, [
                'total' => 50.00, 'status' => Invoice::STATUS_ISSUED,
                'expiration_date' => now()->subDays(5)->toDateString(),
            ]);
            // Paid - must not count toward either figure.
            $this->makeInvoice($customer, [
                'total' => 999.00, 'status' => Invoice::STATUS_PAID,
                'expiration_date' => now()->subDays(5)->toDateString(),
            ]);
            // Cancelled - must not count toward either figure.
            $this->makeInvoice($customer, [
                'total' => 999.00, 'status' => Invoice::STATUS_CANCELLED,
                'expiration_date' => now()->subDays(5)->toDateString(),
            ]);

            $token = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));

        $response->assertStatus(200);
        $this->assertEquals(150.00, $response->json('summary.total_outstanding'));
        $this->assertEquals(50.00, $response->json('summary.overdue_amount'));
        $this->assertEquals(2, $response->json('summary.unpaid_invoices_count'));

        $tenant->delete();
    }

    // ── Rate limiting ──────────────────────────────────────────────────

    /** @test */
    public function the_portal_endpoint_is_rate_limited(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        $tenant->run(fn () => $this->makeCustomer());

        for ($i = 0; $i < 20; $i++) {
            $this->getJson($this->apiUrl($domain, '/api/portal/' . Str::random(64)));
        }

        $response = $this->getJson($this->apiUrl($domain, '/api/portal/' . Str::random(64)));

        $response->assertStatus(429);

        $tenant->delete();
    }

    // ── Step 2: the page-shell route ────────────────────────────────────

    /**
     * GET /portal/{token} (routes/tenant.php, no /api prefix) must serve
     * the Vue app shell - not the JSON data endpoint, which Step 2 moved
     * to /api/portal/{token} (tested above). The Vue router's own
     * 'client-portal' route then reads the token from the URL client-side
     * and calls the JSON endpoint itself - this route never touches
     * ClientPortalService at all, valid or not, any token renders it.
     */
    /** @test */
    public function the_portal_page_route_serves_the_spa_shell_regardless_of_token_validity(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        $tenant->run(fn () => $this->makeCustomer());

        $response = $this->get($this->apiUrl($domain, '/portal/not-a-real-token'));

        $response->assertStatus(200);
        $response->assertSee('id="app"', false);

        $tenant->delete();
    }

    // ── Step 3: secure document downloads ───────────────────────────────

    /** @test */
    public function an_invoice_pdf_belonging_to_the_portal_customer_can_be_downloaded(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $invoice = null;
        $tenant->run(function () use (&$token, &$invoice) {
            $customer = $this->makeCustomer();
            $invoice  = $this->makeInvoice($customer);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/pdf'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        // The filename is derived from the invoice's own display number,
        // never a raw filesystem path.
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('/', $disposition);

        $tenant->delete();
    }

    /** @test */
    public function another_customers_invoice_pdf_returns_404(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $otherInvoice = null;
        $tenant->run(function () use (&$token, &$otherInvoice) {
            $customerA    = $this->makeCustomer(['company_name' => 'Customer A']);
            $customerB    = $this->makeCustomer(['company_name' => 'Customer B']);
            $otherInvoice = $this->makeInvoice($customerB);
            $token        = app(ClientPortalService::class)->createAccess($customerA);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $otherInvoice->uuid . '/pdf'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function a_draft_invoice_cannot_be_downloaded_via_the_portal(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $invoice = null;
        $tenant->run(function () use (&$token, &$invoice) {
            $customer = $this->makeCustomer();
            $invoice  = $this->makeInvoice($customer, ['status' => Invoice::STATUS_DRAFT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/pdf'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function a_quote_pdf_belonging_to_the_portal_customer_can_be_downloaded(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/pdf'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $tenant->delete();
    }

    /** @test */
    public function another_customers_quote_pdf_returns_404(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $otherQuote = null;
        $tenant->run(function () use (&$token, &$otherQuote) {
            $customerA  = $this->makeCustomer(['company_name' => 'Customer A']);
            $customerB  = $this->makeCustomer(['company_name' => 'Customer B']);
            $otherQuote = $this->makeQuote($customerB);
            $token      = app(ClientPortalService::class)->createAccess($customerA);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $otherQuote->uuid . '/pdf'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function a_draft_quote_cannot_be_downloaded_via_the_portal(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_DRAFT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/pdf'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function an_invoice_ubl_belonging_to_the_portal_customer_is_valid_xml_and_passes_validation(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $invoice = null;
        $tenant->run(function () use (&$token, &$invoice) {
            $customer = $this->makeCustomer();
            $invoice  = $this->makeInvoice($customer, [
                'sub_total' => 100.00, 'vta' => 21.00, 'vta21' => 21.00, 'total' => 121.00,
            ]);
            Cart::create([
                'cartable_type' => Invoice::class, 'cartable_id' => $invoice->id,
                'description' => 'Consulting', 'qty' => 1, 'unite' => 'piece',
                'price' => 100, 'discount' => 0, 'vta' => 21, 'total' => 100,
                'tax_treatment' => 'taxable',
            ]);
            $token = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/ubl'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringStartsWith('<?xml', $response->getContent());

        $result = (new UblValidator())->validateXsd($response->getContent());
        $this->assertTrue($result->valid, 'UBL output failed the official UBL 2.1 XSD: ' . json_encode(array_map(
            fn (UblValidationError $e) => $e->toArray(),
            $result->errors
        )));

        $tenant->delete();
    }

    /** @test */
    public function another_customers_invoice_ubl_returns_404(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $otherInvoice = null;
        $tenant->run(function () use (&$token, &$otherInvoice) {
            $customerA    = $this->makeCustomer(['company_name' => 'Customer A']);
            $customerB    = $this->makeCustomer(['company_name' => 'Customer B']);
            $otherInvoice = $this->makeInvoice($customerB);
            $token        = app(ClientPortalService::class)->createAccess($customerA);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $otherInvoice->uuid . '/ubl'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function an_invalid_token_cannot_download_any_document(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeInvoice($this->makeCustomer());
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . Str::random(64) . '/invoices/' . $invoice->uuid . '/pdf'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function a_revoked_token_cannot_download_any_document(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $invoice = null;
        $tenant->run(function () use (&$token, &$invoice) {
            $customer = $this->makeCustomer();
            $invoice  = $this->makeInvoice($customer);
            $service  = app(ClientPortalService::class);
            $token    = $service->createAccess($customer);
            $service->revoke($customer);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/pdf'));

        $response->assertStatus(410);

        $tenant->delete();
    }

    /** @test */
    public function a_regenerated_old_token_cannot_download_any_document(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $oldToken = null;
        $invoice = null;
        $tenant->run(function () use (&$oldToken, &$invoice) {
            $customer = $this->makeCustomer();
            $invoice  = $this->makeInvoice($customer);
            $service  = app(ClientPortalService::class);
            $oldToken = $service->createAccess($customer);
            $service->regenerateAccess($customer);
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $oldToken . '/invoices/' . $invoice->uuid . '/pdf'));

        $response->assertStatus(410);

        $tenant->delete();
    }

    /** @test */
    public function a_document_generation_failure_never_exposes_internal_details(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $invoice = null;
        $tenant->run(function () use (&$token, &$invoice) {
            $customer = $this->makeCustomer();
            $invoice  = $this->makeInvoice($customer);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        // Force the XSD step to fail without needing a genuinely
        // schema-invalid invoice - same technique
        // EInvoiceProfileRegistryTest/InvoiceUblExportTest already use to
        // prove the error path, not that it happens to fail in practice.
        $this->app->bind(UblValidator::class, function () {
            return new class extends UblValidator {
                public function validateXsd(string $xml): UblValidationResult
                {
                    return new UblValidationResult(false, [
                        new UblValidationError('/var/www/fakturalista/storage/secret-path leaked', 1, 1, 2, 1871),
                    ]);
                }
            };
        });

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/ubl'));

        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('/var/www/fakturalista', $response->getContent());
        $this->assertStringNotContainsString('leaked', $response->getContent());
        $this->assertStringNotContainsString('cvc-', $response->getContent());

        $tenant->delete();
    }

    /** @test */
    public function tenant_isolation_holds_for_document_downloads(): void
    {
        [$tenantA] = $this->makeTenant();
        [$tenantB, $domainB] = $this->makeTenant();

        $tokenA = null;
        $invoiceA = null;
        $tenantA->run(function () use (&$tokenA, &$invoiceA) {
            $customer = $this->makeCustomer();
            $invoiceA = $this->makeInvoice($customer);
            $tokenA   = app(ClientPortalService::class)->createAccess($customer);
        });

        // Tenant A's own token and invoice uuid, requested against tenant
        // B's domain - tenant B's database simply has neither row.
        $response = $this->get($this->apiUrl($domainB, '/api/portal/' . $tokenA . '/invoices/' . $invoiceA->uuid . '/pdf'));

        $response->assertStatus(404);

        $tenantA->delete();
        $tenantB->delete();
    }

    /** @test */
    public function document_downloads_remain_rate_limited(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $invoice = null;
        $tenant->run(function () use (&$token, &$invoice) {
            $customer = $this->makeCustomer();
            $invoice  = $this->makeInvoice($customer);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        for ($i = 0; $i < 20; $i++) {
            $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/pdf'));
        }

        $response = $this->get($this->apiUrl($domain, '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/pdf'));

        $response->assertStatus(429);

        $tenant->delete();
    }

    // ── Step 4: quote accept/reject ─────────────────────────────────────

    /** @test */
    public function a_customer_can_accept_their_own_sent_quote(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(200);
        $this->assertSame(Quote::STATUS_ACCEPTED, $response->json('status'));

        $tenant->delete();
    }

    /** @test */
    public function accepting_a_quote_records_accepted_at(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(200);
        $this->assertNotNull($response->json('accepted_at'));

        $tenant->run(function () use ($quote) {
            $fresh = Quote::where('uuid', $quote->uuid)->first();
            $this->assertSame(Quote::STATUS_ACCEPTED, $fresh->status);
            $this->assertNotNull($fresh->accepted_at);
            $this->assertNull($fresh->rejected_at);
        });

        $tenant->delete();
    }

    /** @test */
    public function a_customer_can_reject_their_own_sent_quote(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/reject'));

        $response->assertStatus(200);
        $this->assertSame(Quote::STATUS_REJECTED, $response->json('status'));

        $tenant->delete();
    }

    /** @test */
    public function rejecting_a_quote_records_rejected_at(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/reject'));

        $response->assertStatus(200);
        $this->assertNotNull($response->json('rejected_at'));

        $tenant->run(function () use ($quote) {
            $fresh = Quote::where('uuid', $quote->uuid)->first();
            $this->assertSame(Quote::STATUS_REJECTED, $fresh->status);
            $this->assertNotNull($fresh->rejected_at);
            $this->assertNull($fresh->accepted_at);
        });

        $tenant->delete();
    }

    /** @test */
    public function a_quote_cannot_be_accepted_twice(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'))->assertStatus(200);
        $second = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $second->assertStatus(422);

        $tenant->delete();
    }

    /** @test */
    public function a_quote_cannot_be_rejected_twice(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/reject'))->assertStatus(200);
        $second = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/reject'));

        $second->assertStatus(422);

        $tenant->delete();
    }

    /** @test */
    public function a_rejected_quote_cannot_then_be_accepted(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/reject'))->assertStatus(200);
        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(422);

        $tenant->delete();
    }

    /** @test */
    public function an_accepted_quote_cannot_then_be_rejected(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'))->assertStatus(200);
        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/reject'));

        $response->assertStatus(422);

        $tenant->delete();
    }

    /** @test */
    public function a_customer_cannot_act_on_another_customers_quote(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $otherQuote = null;
        $tenant->run(function () use (&$token, &$otherQuote) {
            $customerA  = $this->makeCustomer(['company_name' => 'Customer A']);
            $customerB  = $this->makeCustomer(['company_name' => 'Customer B']);
            $otherQuote = $this->makeQuote($customerB, ['status' => Quote::STATUS_SENT]);
            $token      = app(ClientPortalService::class)->createAccess($customerA);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $otherQuote->uuid . '/accept'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function a_draft_quote_cannot_be_accepted_or_rejected(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_DRAFT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function a_cancelled_quote_cannot_be_accepted_or_rejected(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_CANCELLED]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(422);

        $tenant->delete();
    }

    /** @test */
    public function a_converted_quote_cannot_be_accepted_or_rejected(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_CONVERTED]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(422);

        $tenant->delete();
    }

    /** @test */
    public function an_invalid_token_cannot_accept_or_reject_a_quote(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $quote = null;
        $tenant->run(function () use (&$quote) {
            $quote = $this->makeQuote($this->makeCustomer(), ['status' => Quote::STATUS_SENT]);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . Str::random(64) . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(404);

        $tenant->delete();
    }

    /** @test */
    public function a_revoked_token_cannot_accept_or_reject_a_quote(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $service  = app(ClientPortalService::class);
            $token    = $service->createAccess($customer);
            $service->revoke($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(410);

        $tenant->delete();
    }

    /** @test */
    public function tenant_isolation_holds_for_quote_accept_reject(): void
    {
        [$tenantA] = $this->makeTenant();
        [$tenantB, $domainB] = $this->makeTenant();

        $tokenA = null;
        $quoteA = null;
        $tenantA->run(function () use (&$tokenA, &$quoteA) {
            $customer = $this->makeCustomer();
            $quoteA   = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $tokenA   = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domainB, '/api/portal/' . $tokenA . '/quotes/' . $quoteA->uuid . '/accept'));

        $response->assertStatus(404);

        $tenantA->delete();
        $tenantB->delete();
    }

    /** @test */
    public function quote_accept_reject_remains_rate_limited(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $token = null;
        $quote = null;
        $tenant->run(function () use (&$token, &$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        // The shared client-portal limiter is 20/min - burn it with GET
        // requests to the already-tested show() endpoint, then confirm
        // the POST accept endpoint is governed by the very same bucket.
        for ($i = 0; $i < 20; $i++) {
            $this->getJson($this->apiUrl($domain, '/api/portal/' . $token));
        }

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'));

        $response->assertStatus(429);

        $tenant->delete();
    }

    /** @test */
    public function an_accepted_quote_can_still_be_converted_to_an_invoice_by_staff(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $quote = null;
        $tenant->run(function () use (&$quote) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_SENT]);
            Cart::create([
                'cartable_type' => Quote::class, 'cartable_id' => $quote->id,
                'description' => 'Service', 'qty' => 1, 'unite' => 'piece',
                'price' => 50, 'discount' => 0, 'vta' => 0, 'total' => 50,
                'tax_treatment' => 'taxable',
            ]);
        });

        $token = null;
        $tenant->run(function () use ($quote, &$token) {
            $token = app(ClientPortalService::class)->createAccess($quote->customer);
        });

        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'))->assertStatus(200);

        // The existing conversion service (unchanged by this step) still
        // accepts the quote exactly as it did before it was ever
        // "accepted" - QuoteToInvoiceService::convert() only ever
        // rejects an already-converted quote.
        $tenant->run(function () use ($quote) {
            $fresh   = Quote::where('uuid', $quote->uuid)->first();
            $invoice = app(\App\Services\QuoteToInvoiceService::class)->convert($fresh);

            $this->assertNotNull($invoice);
            $this->assertSame(Quote::STATUS_CONVERTED, $fresh->fresh()->status);
        });

        $tenant->delete();
    }

    /** @test */
    public function resending_an_accepted_quote_from_admin_does_not_reopen_the_decision(): void
    {
        [$tenant] = $this->makeTenant();

        $tenant->run(function () {
            \Illuminate\Support\Facades\Mail::fake();

            $customer = $this->makeCustomer(['email' => 'client@example.test']);
            $quote    = $this->makeQuote($customer, [
                'status'      => Quote::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            $pdf = $this->createMock(\App\Services\QuotePdfService::class);
            $pdf->method('generate')->willReturn('%PDF-fake');

            $response = app(\App\Http\Controllers\QuoteController::class)->send($quote, $pdf);

            $this->assertSame(200, $response->getStatusCode());
            $fresh = $quote->fresh();
            $this->assertSame(Quote::STATUS_ACCEPTED, $fresh->status);
            $this->assertNotNull($fresh->accepted_at);
        });

        $tenant->delete();
    }

    /** @test */
    public function a_quote_accept_or_reject_response_is_a_translated_generic_422_when_already_decided(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        $quote = null;
        $token = null;
        $tenant->run(function () use (&$quote, &$token) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_REJECTED, 'rejected_at' => now()]);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $response = $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/reject'));

        $response->assertStatus(422);
        $this->assertSame(__('quote.portal_cannot_reject'), $response->json('message'));

        $tenant->delete();
    }

    // ── Step 5: accepted quotes frozen, rejected not convertible, notifications ──

    /** @test */
    public function an_accepted_quote_cannot_be_edited_by_staff(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        tenancy()->initialize($tenant);
        $customer = $this->makeCustomer();
        $other    = $this->makeCustomer();
        $quote    = $this->makeQuote($customer, ['status' => Quote::STATUS_ACCEPTED, 'accepted_at' => now()]);
        $this->actingAs(User::first(), 'api');

        $response = $this->putJson($this->apiUrl($domain, '/api/quotes/' . $quote->uuid), [
            'customer_id'     => $other->uuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(10)->toDateString(),
            'status'          => Quote::STATUS_DRAFT,
            'discount_rate'   => 50,
            'carts'           => [['name' => 'X', 'qty' => 9, 'price' => 999, 'vta' => 0, 'tax_treatment' => 'taxable']],
        ]);

        $response->assertStatus(422);
        $this->assertSame(__('quote.locked_accepted'), $response->json('message'));

        $fresh = $quote->fresh();
        $this->assertSame(Quote::STATUS_ACCEPTED, $fresh->status);
        $this->assertSame((int) $customer->id, (int) $fresh->customer_id);
        $this->assertEquals(50.00, (float) $fresh->total);

        tenancy()->end();
        $tenant->delete();
    }

    /** @test */
    public function a_rejected_quote_cannot_be_converted_to_an_invoice(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        tenancy()->initialize($tenant);
        $quote = $this->makeQuote($this->makeCustomer(), ['status' => Quote::STATUS_REJECTED, 'rejected_at' => now()]);
        $this->actingAs(User::first(), 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/quotes/' . $quote->uuid . '/convert'));

        $response->assertStatus(422);
        $this->assertSame(__('quote.cannot_convert_rejected'), $response->json('message'));
        $this->assertSame(Quote::STATUS_REJECTED, $quote->fresh()->status);
        $this->assertSame(0, Invoice::count());

        tenancy()->end();
        $tenant->delete();
    }

    /** @test */
    public function the_company_is_notified_when_a_customer_accepts_or_rejects_a_quote(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        [$tenant, $domain] = $this->makeTenant();
        $tenant->update(['owner_email' => 'owner@example.test', 'owner_name' => 'Owner']);

        $accepted = $rejected = $token = null;
        $tenant->run(function () use (&$accepted, &$rejected, &$token) {
            $customer = $this->makeCustomer();
            $accepted = $this->makeQuote($customer);
            $rejected = $this->makeQuote($customer);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $accepted->uuid . '/accept'))->assertStatus(200);
        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $rejected->uuid . '/reject'))->assertStatus(200);
        // A refused second attempt must not notify again.
        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $accepted->uuid . '/accept'))->assertStatus(422);

        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\QuoteDecisionNotificationMail::class, 2);
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\QuoteDecisionNotificationMail::class,
            fn ($mail) => $mail->quote->uuid === $accepted->uuid && $mail->decision === Quote::STATUS_ACCEPTED && $mail->hasTo('owner@example.test'));
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\QuoteDecisionNotificationMail::class,
            fn ($mail) => $mail->quote->uuid === $rejected->uuid && $mail->decision === Quote::STATUS_REJECTED);

        // The email itself renders, with its translated copy, in every locale.
        $tenant->run(function () use ($accepted) {
            $mail = \Illuminate\Support\Facades\Mail::queued(\App\Mail\QuoteDecisionNotificationMail::class)
                ->first(fn ($m) => $m->decision === Quote::STATUS_ACCEPTED);
            foreach (['fr', 'es', 'en', 'ar'] as $locale) {
                $html = $mail->locale($locale)->render();
                $this->assertStringContainsString($accepted->reference, $html);
                $this->assertStringContainsString(e(__('emails.quote_decision_notification.hero_title_accepted', [], $locale)), $html);
            }
        });

        $tenant->delete();
    }

    /** @test */
    public function no_decision_notification_is_sent_when_the_preference_is_off(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        [$tenant, $domain] = $this->makeTenant(['notification_preferences' => ['quote_decision' => false]]);
        $tenant->update(['owner_email' => 'owner@example.test']);

        $quote = $token = null;
        $tenant->run(function () use (&$quote, &$token) {
            $customer = $this->makeCustomer();
            $quote    = $this->makeQuote($customer);
            $token    = app(ClientPortalService::class)->createAccess($customer);
        });

        $this->postJson($this->apiUrl($domain, '/api/portal/' . $token . '/quotes/' . $quote->uuid . '/accept'))->assertStatus(200);

        \Illuminate\Support\Facades\Mail::assertNothingQueued();

        $tenant->delete();
    }

    // ── Cleanup after Step 5: deletion protection, translations ─────────

    /** @test */
    public function an_accepted_quote_cannot_be_deleted_individually_but_a_draft_can(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        tenancy()->initialize($tenant);
        $customer = $this->makeCustomer();
        $accepted = $this->makeQuote($customer, ['status' => Quote::STATUS_ACCEPTED, 'accepted_at' => now()]);
        $draft    = $this->makeQuote($customer, ['status' => Quote::STATUS_DRAFT]);
        $this->actingAs(User::first(), 'api');

        $response = $this->deleteJson($this->apiUrl($domain, '/api/quotes/' . $accepted->uuid));
        $response->assertStatus(422);
        $this->assertSame(__('quote.cannot_delete_accepted'), $response->json('message'));
        $this->assertNotNull(Quote::find($accepted->id));

        $deleted = $this->deleteJson($this->apiUrl($domain, '/api/quotes/' . $draft->uuid));
        $deleted->assertStatus(200);
        $this->assertSame(__('quote.deleted'), $deleted->json('message'));
        foreach (['fr', 'es', 'en', 'ar'] as $locale) {
            $this->assertNotSame('quote.deleted', trans('quote.deleted', [], $locale));
        }
        $this->assertNull(Quote::find($draft->id));

        tenancy()->end();
        $tenant->delete();
    }

    /** @test */
    public function bulk_delete_skips_accepted_quotes(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        tenancy()->initialize($tenant);
        $customer = $this->makeCustomer();
        $accepted = $this->makeQuote($customer, ['status' => Quote::STATUS_ACCEPTED, 'accepted_at' => now()]);
        $draft    = $this->makeQuote($customer, ['status' => Quote::STATUS_DRAFT]);
        $rejected = $this->makeQuote($customer, ['status' => Quote::STATUS_REJECTED, 'rejected_at' => now()]);
        $this->actingAs(User::first(), 'api');

        // Only accepted quotes selected -> nothing deleted, translated 422.
        $only = $this->postJson($this->apiUrl($domain, '/api/quotes/bulk-delete'), ['ids' => [$accepted->uuid]]);
        $only->assertStatus(422);
        $this->assertSame(__('quote.cannot_delete_accepted'), $only->json('message'));
        $this->assertNotNull(Quote::find($accepted->id));

        // Mixed selection -> eligible ones deleted, accepted one skipped.
        $mixed = $this->postJson($this->apiUrl($domain, '/api/quotes/bulk-delete'), [
            'ids' => [$accepted->uuid, $draft->uuid, $rejected->uuid],
        ]);
        $mixed->assertStatus(200);
        $this->assertEqualsCanonicalizing([$draft->uuid, $rejected->uuid], $mixed->json('deleted'));
        $this->assertSame([$accepted->uuid], $mixed->json('skipped'));
        $this->assertSame(__('quote.bulk_deleted_with_skipped', ['count' => 2, 'skipped' => 1]), $mixed->json('message'));

        $this->assertNotNull(Quote::find($accepted->id));
        $this->assertNull(Quote::find($draft->id));
        $this->assertNull(Quote::find($rejected->id));

        tenancy()->end();
        $tenant->delete();
    }

    /** @test */
    public function the_already_converted_conversion_error_is_translated_in_every_locale(): void
    {
        [$tenant] = $this->makeTenant();

        $tenant->run(function () {
            $quote   = $this->makeQuote($this->makeCustomer(), ['status' => Quote::STATUS_CONVERTED]);
            $service = app(\App\Services\QuoteToInvoiceService::class);

            foreach (['fr', 'es', 'en', 'ar'] as $locale) {
                app()->setLocale($locale);
                try {
                    $service->convert($quote);
                    $this->fail('A converted quote must not be converted again.');
                } catch (\RuntimeException $e) {
                    $this->assertNotSame('quote.already_converted', $e->getMessage());
                    $this->assertSame(trans('quote.already_converted', [], $locale), $e->getMessage());
                }
            }
        });

        $tenant->delete();
    }

    /** @test */
    public function spanish_has_every_notification_email_translation(): void
    {
        $groups = [
            'invoice_due_reminder', 'invoice_overdue_reminder', 'invoice_paid_notification',
            'quote_converted_notification', 'quote_decision_notification',
        ];

        $fr = require lang_path('fr/emails.php');
        $es = require lang_path('es/emails.php');

        foreach ($groups as $group) {
            $this->assertArrayHasKey($group, $es, "es/emails.php is missing '$group'");
            $this->assertSame([], array_diff(array_keys($fr[$group]), array_keys($es[$group])), "es/emails.php '$group' is missing keys");
            $this->assertNotSame(
                trans("emails.$group.subject" . ($group === 'quote_decision_notification' ? '_accepted' : ''), [], 'fr'),
                trans("emails.$group.subject" . ($group === 'quote_decision_notification' ? '_accepted' : ''), [], 'es'),
                "'$group' in Spanish still falls back to French"
            );
        }
    }
}
