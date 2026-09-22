<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Pdf\TemplateRendererService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Morocco Phase 1A §4 - docs/morocco-phase-1a-implementation.md.
 *
 * The bug this phase fixes: business-document (PDF/email) language and
 * currency used to follow whichever staff user happened to be logged in
 * (App::getLocale()), not the tenant's own configuration. These tests
 * simulate exactly that mismatch - a staff member with a DIFFERENT UI
 * locale than their own tenant's business locale - and assert the
 * document still resolves correctly, and that rendering it never mutates
 * the staff member's own UI locale.
 */
class BusinessDocumentLocaleTest extends TestCase
{
    private function makeTenant(string $id): Tenant
    {
        $tenant = Tenant::create(['id' => $id]);
        $tenant->domains()->create(['domain' => $id . '.fakturalista.test']);

        return $tenant;
    }

    private function issuedInvoice(): Invoice
    {
        $customer = Customer::factory()->create();

        return Invoice::create([
            'uuid'           => Str::uuid()->toString(),
            'reference'      => 'DOC-' . uniqid(),
            'customer_id'    => $customer->id,
            'date'           => now()->toDateString(),
            'expiration_date'=> now()->addDays(30)->toDateString(),
            'status'         => Invoice::STATUS_ISSUED,
            'sub_total'      => 100,
            'total'          => 121,
            'vta'            => 21,
            'vta4'           => 0,
            'vta10'          => 0,
            'vta21'          => 21,
            'discount_rate'  => 0,
            'discount_amount'=> 0,
        ]);
    }

    /** @test */
    public function moroccan_tenants_pdf_resolves_to_french_even_if_the_staff_users_ui_locale_is_spanish(): void
    {
        $tenant = $this->makeTenant('test-doclocale-ma-' . uniqid());

        $tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Entreprise Marocaine SARL',
                'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr', 'timezone' => 'Africa/Casablanca',
            ]);
            $invoice = $this->issuedInvoice();

            // Simulates a staff member whose OWN personal UI locale is
            // Spanish, operating a Moroccan tenant.
            App::setLocale('es');

            $this->assertEquals('fr', app(TenantContextService::class)->locale());
            $this->assertEquals('MAD', app(TenantContextService::class)->currency());

            // Morocco Phase 2A: the render()-succeeds/App::getLocale()
            // assertions above never actually inspected the rendered TEXT -
            // that blind spot is exactly how the real bug (every __()
            // label inside the PDF Blade components silently followed the
            // staff user's own 'es' locale instead of this tenant's 'fr',
            // while the inline match($locale) labels like "Facture"
            // stayed correct) went undetected. Asserting the actual French
            // labels here closes that gap.
            //
            // The sub-component is rendered from INSIDE the composer
            // callback, synchronously, while App::setLocale($locale) is
            // still active inside render()'s own try block - capturing
            // $view->getData() and re-rendering the component AFTER
            // render() returns would read it back once the finally block
            // has already restored the staff's own locale, silently
            // asserting nothing.
            $html = null;
            View::composer('pdf.document', function ($view) use (&$html) {
                $html = view('pdf.components._header', $view->getData())->render();
            });
            app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertStringContainsString('Date de facture', $html);
            $this->assertStringNotContainsString('Fecha de factura', $html);

            $pdf = app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertNotEmpty($pdf);
            $this->assertStringStartsWith('%PDF', $pdf);
        });

        $tenant->delete();
    }

    /** @test */
    public function spanish_tenants_pdf_resolves_to_spanish_and_euro_even_if_the_staff_users_ui_locale_is_french(): void
    {
        $tenant = $this->makeTenant('test-doclocale-es-' . uniqid());

        $tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Empresa Española SL',
                'country_code' => 'ES', 'currency' => 'EUR', 'locale' => 'es', 'timezone' => 'Europe/Madrid',
            ]);
            $invoice = $this->issuedInvoice();

            App::setLocale('fr');

            $this->assertEquals('es', app(TenantContextService::class)->locale());
            $this->assertEquals('EUR', app(TenantContextService::class)->currency());

            $html = null;
            View::composer('pdf.document', function ($view) use (&$html) {
                $html = view('pdf.components._header', $view->getData())->render();
            });
            app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertStringContainsString('Fecha de factura', $html);
            $this->assertStringNotContainsString('Date de facture', $html);

            $pdf = app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertNotEmpty($pdf);
            $this->assertStringStartsWith('%PDF', $pdf);
        });

        $tenant->delete();
    }

    /** @test */
    public function rendering_a_business_document_never_changes_the_staff_users_own_ui_locale(): void
    {
        $tenant = $this->makeTenant('test-doclocale-noleak-' . uniqid());

        $tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Entreprise Marocaine SARL',
                'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr',
            ]);
            $invoice = $this->issuedInvoice();

            App::setLocale('es');
            app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');

            $this->assertEquals('es', App::getLocale(), 'Rendering a PDF must never mutate the staff UI locale.');
        });

        $tenant->delete();
    }
}
