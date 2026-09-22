<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceNumberingService;
use App\Services\Verifactu\VerifactuCertificateService;
use App\Services\Verifactu\VerifactuChainService;
use App\Services\Verifactu\VerifactuHashService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `verifactu:aeat-test` - the ONLY command allowed to reach the real
 * AEAT network, and only when a human runs it explicitly (never during
 * PHPUnit - this test itself always fakes HTTP, per
 * docs/verifactu-aeat-connectivity.md §16/§17).
 */
class VerifactuAeatTestCommandTest extends TestCase
{
    protected Tenant $tenant;
    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'verifactu.producer.name' => 'Fakturalista Test Producer',
            'verifactu.producer.id_country' => 'MA',
            'verifactu.producer.id_type' => '06',
            'verifactu.producer.id' => 'RC-TEST-12345',
            'verifactu.system.name' => 'Fakturalista',
            'verifactu.system.id' => 'FK',
            'verifactu.system.version' => '1.0.0-test',
            'verifactu.system.only_verifactu' => true,
            'verifactu.system.multi_ot' => true,
        ]);

        $this->tenant = Tenant::create(['id' => 'test-vfcmd-' . uniqid()]);
        $this->domain = 'test-vfcmd-' . uniqid() . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
    }

    protected function tearDown(): void
    {
        $this->tenant->delete();
        parent::tearDown();
    }

    /** @test */
    public function command_refuses_when_environment_is_not_test(): void
    {
        config(['verifactu.aeat.environment' => 'production']);

        $this->artisan('verifactu:aeat-test', ['tenant' => $this->tenant->getTenantKey(), '--force' => true])
            ->assertExitCode(1);

        Http::fake();
        Http::assertNothingSent();
    }

    /** @test */
    public function command_requires_an_explicit_record_id(): void
    {
        $this->artisan('verifactu:aeat-test', ['tenant' => $this->tenant->getTenantKey()])
            ->assertExitCode(1);
    }

    /** @test */
    public function force_flag_skips_confirmation_and_sends_via_the_faked_transport(): void
    {
        Http::fake([
            'https://prewww1.aeat.es/*' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sfR="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd" xmlns:sf="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
  <soapenv:Body>
    <sfR:RespuestaRegFactuSistemaFacturacion>
      <sfR:CSV>CSV-CMD-TEST</sfR:CSV>
      <sfR:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Test</sf:NombreRazon><sf:NIF>89890001K</sf:NIF></sf:ObligadoEmision></sfR:Cabecera>
      <sfR:EstadoEnvio>Correcto</sfR:EstadoEnvio>
      <sfR:RespuestaLinea><sf:IDFactura><sf:IDEmisorFactura>89890001K</sf:IDEmisorFactura><sf:NumSerieFactura>X</sf:NumSerieFactura><sf:FechaExpedicionFactura>01-01-2026</sf:FechaExpedicionFactura></sf:IDFactura><sfR:EstadoRegistro>Correcto</sfR:EstadoRegistro></sfR:RespuestaLinea>
    </sfR:RespuestaRegFactuSistemaFacturacion>
  </soapenv:Body>
</soapenv:Envelope>',
                200
            ),
        ]);

        $recordId = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Empresa Ficticia SL', 'tax_id' => '89890001K', 'invoice_prefix' => 'INV',
                'onboarding_completed_at' => now(), 'verifactu_installation_number' => 'TEST-INSTALL-1',
            ]);
            $fixture = $this->makeTestPkcs12();
            app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

            $customer = Customer::factory()->create(['tax_id' => 'B00000000']);
            $invoice  = Invoice::create([
                'uuid' => Str::uuid()->toString(), 'reference' => 'DRAFT-CMD', 'customer_id' => $customer->id,
                'date' => now()->toDateString(), 'expiration_date' => now()->addDays(30)->toDateString(),
                'status' => Invoice::STATUS_DRAFT, 'sub_total' => 100, 'total' => 121, 'vta' => 21,
                'vta4' => 0, 'vta10' => 0, 'vta21' => 21, 'discount_rate' => 0, 'discount_amount' => 0,
                'descripcion_operacion' => 'Servicios de prueba',
            ]);
            $company = CompanyProfile::first();
            app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $company);
            $invoice->status = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->save();

            $record = (new VerifactuChainService(new VerifactuHashService()))->recordAlta($invoice, $company);

            return $record->id;
        });

        $this->artisan('verifactu:aeat-test', [
            'tenant'   => $this->tenant->getTenantKey(),
            '--record' => $recordId,
            '--force'  => true,
        ])->assertExitCode(0);

        Http::assertSentCount(1);
    }

    private function makeTestPkcs12(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Fakturalista Test Cert', 'countryName' => 'ES'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        $passphrase = 'test-pass-' . uniqid();
        openssl_pkcs12_export($cert, $pkcs12, $key, $passphrase);

        return ['pkcs12' => $pkcs12, 'passphrase' => $passphrase];
    }
}
