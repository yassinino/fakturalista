<?php

namespace Tests\Feature;

use App\Jobs\SendVerifactuRecordToAeatJob;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Verifactu\VerifactuChainState;
use App\Models\Verifactu\VerifactuSubmission;
use App\Models\Verifactu\VerifactuSubmissionAttempt;
use App\Services\InvoiceNumberingService;
use App\Services\Verifactu\VerifactuCertificateService;
use App\Services\Verifactu\VerifactuChainService;
use App\Services\Verifactu\VerifactuHashService;
use App\Services\Verifactu\VerifactuSubmissionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end (mocked transport only - NO automated test may call the
 * real AEAT service, see docs/verifactu-aeat-connectivity.md §16):
 * real tenant DB -> real VerifactuRecord -> real VerifactuSubmission ->
 * SendVerifactuRecordToAeatJob with Http::fake() standing in for AEAT.
 */
class SendVerifactuRecordToAeatJobTest extends TestCase
{
    protected Tenant $tenant;
    protected string $domain;
    protected CompanyProfile $company;

    private const NIF = '89890001K';
    private const TEST_ENDPOINT = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
    private const NS_R = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd';
    private const NS_SF = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

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

        $this->tenant = Tenant::create(['id' => 'test-vfjob-' . uniqid()]);
        $this->domain = 'test-vfjob-' . uniqid() . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);

        tenancy()->initialize($this->tenant);

        $this->company = CompanyProfile::create([
            'legal_name'                     => 'Empresa Ficticia de Pruebas SL',
            'tax_id'                         => self::NIF,
            'invoice_prefix'                 => 'INV',
            'onboarding_completed_at'        => now(),
            'verifactu_installation_number'  => 'TEST-INSTALL-1',
        ]);

        $fixture = $this->makeTestPkcs12();
        app(VerifactuCertificateService::class)->upload(self::NIF, $fixture['pkcs12'], $fixture['passphrase']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
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

    private function issuedInvoiceWithRecord(): \App\Models\Verifactu\VerifactuRecord
    {
        $customer = Customer::factory()->create(['tax_id' => 'B00000000']);

        $invoice = Invoice::create([
            'uuid'                  => Str::uuid()->toString(),
            'reference'             => 'DRAFT-TMP',
            'customer_id'           => $customer->id,
            'date'                  => now()->toDateString(),
            'expiration_date'       => now()->addDays(30)->toDateString(),
            'status'                => Invoice::STATUS_DRAFT,
            'sub_total'             => 100.00,
            'total'                 => 121.00,
            'vta'                   => 21.00,
            'vta4'                  => 0,
            'vta10'                 => 0,
            'vta21'                 => 21.00,
            'discount_rate'         => 0,
            'discount_amount'       => 0,
            'descripcion_operacion' => 'Servicios de consultoría de prueba',
        ]);

        app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $this->company);
        $invoice->status    = Invoice::STATUS_ISSUED;
        $invoice->issued_at = now();
        $invoice->save();

        return (new VerifactuChainService(new VerifactuHashService()))->recordAlta($invoice, $this->company);
    }

    private function aeatXmlResponse(string $estadoEnvio, ?string $estadoRegistro, ?string $csv = null, ?int $errorCode = null, ?string $errorDescription = null, int $tiempoEspera = 60): string
    {
        $csvXml = $csv ? "<sfR:CSV>{$csv}</sfR:CSV>" : '';
        $errXml = $errorCode ? "<sfR:CodigoErrorRegistro>{$errorCode}</sfR:CodigoErrorRegistro><sfR:DescripcionErrorRegistro>{$errorDescription}</sfR:DescripcionErrorRegistro>" : '';
        $lineXml = $estadoRegistro ? "
            <sfR:RespuestaLinea>
                <sf:IDFactura><sf:IDEmisorFactura>" . self::NIF . "</sf:IDEmisorFactura><sf:NumSerieFactura>X</sf:NumSerieFactura><sf:FechaExpedicionFactura>01-01-2026</sf:FechaExpedicionFactura></sf:IDFactura>
                <sfR:EstadoRegistro>{$estadoRegistro}</sfR:EstadoRegistro>
                {$errXml}
            </sfR:RespuestaLinea>" : '';

        return '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sfR="' . self::NS_R . '" xmlns:sf="' . self::NS_SF . '">
  <soapenv:Body>
    <sfR:RespuestaRegFactuSistemaFacturacion>
      ' . $csvXml . '
      <sfR:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Test</sf:NombreRazon><sf:NIF>' . self::NIF . '</sf:NIF></sf:ObligadoEmision></sfR:Cabecera>
      <sf:TiempoEsperaEnvio>' . $tiempoEspera . '</sf:TiempoEsperaEnvio>
      <sfR:EstadoEnvio>' . $estadoEnvio . '</sfR:EstadoEnvio>
      ' . $lineXml . '
    </sfR:RespuestaRegFactuSistemaFacturacion>
  </soapenv:Body>
</soapenv:Envelope>';
    }

    // ── Happy paths ────────────────────────────────────────────

    /** @test */
    public function successful_accepted_response_updates_the_submission(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto', 'CSV-ACCEPTED-1'), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_ACCEPTED, $submission->status);
        $this->assertEquals('CSV-ACCEPTED-1', $submission->csv);
        $this->assertEquals('Correcto', $submission->aeat_estado_envio);
        $this->assertNotNull($submission->completed_at);
    }

    /** @test */
    public function accepted_with_errors_response_is_persisted_distinctly(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('ParcialmenteCorrecto', 'AceptadoConErrores', 'CSV-2', 111, 'Aviso menor'), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_ACCEPTED_WITH_ERRORS, $submission->status);
        $this->assertEquals(111, $submission->aeat_error_code);
        $this->assertEquals('Aviso menor', $submission->aeat_error_description);
    }

    /** @test */
    public function rejected_response_persists_error_code_and_description(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Incorrecto', 'Incorrecto', null, 9999, 'NIF invalido'), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_REJECTED, $submission->status);
        $this->assertEquals(9999, $submission->aeat_error_code);
        $this->assertEquals('NIF invalido', $submission->aeat_error_description);
        $this->assertNull($submission->csv);
    }

    /** @test */
    public function duplicate_response_is_treated_as_success(): void
    {
        $xml = str_replace(
            '</sfR:RespuestaLinea>',
            '<sf:RegistroDuplicado><sf:IdPeticionRegistroDuplicado>ORIGINAL-1</sf:IdPeticionRegistroDuplicado><sf:EstadoRegistroDuplicado>Correcta</sf:EstadoRegistroDuplicado></sf:RegistroDuplicado></sfR:RespuestaLinea>',
            $this->aeatXmlResponse('Incorrecto', 'Incorrecto')
        );
        Http::fake([self::TEST_ENDPOINT => Http::response($xml, 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_ACCEPTED, $submission->status);
        $this->assertEquals('ORIGINAL-1', $submission->duplicate_of_id_peticion);
    }

    // ── Transport / TLS / SOAP fault failures ───────────────────

    /** @test */
    public function transport_failure_marks_transport_error_and_never_touches_the_invoice(): void
    {
        Http::fake([self::TEST_ENDPOINT => fn () => throw new ConnectionException('Connection timed out')]);

        $record     = $this->issuedInvoiceWithRecord();
        $invoice    = $record->invoice;
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_TRANSPORT_ERROR, $submission->status);
        $this->assertEquals(1, $submission->retry_count);

        // The immutable fiscal record and the invoice remain completely
        // untouched by a network failure.
        $invoice->refresh();
        $this->assertTrue($invoice->isIssued());
        $record->refresh();
        $this->assertNotEmpty($record->huella);
    }

    /** @test */
    public function tls_failure_is_classified_distinctly_from_a_generic_transport_failure(): void
    {
        Http::fake([self::TEST_ENDPOINT => fn () => throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate')]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $attempt = VerifactuSubmissionAttempt::where('verifactu_submission_id', $submission->id)->first();
        $this->assertEquals(VerifactuSubmissionAttempt::OUTCOME_TLS_ERROR, $attempt->outcome);
    }

    /** @test */
    public function soap_fault_is_recorded_as_its_own_outcome(): void
    {
        $fault = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
  <soapenv:Body>
    <soapenv:Fault><faultcode>soapenv:Server</faultcode><faultstring>Malformed</faultstring></soapenv:Fault>
  </soapenv:Body>
</soapenv:Envelope>';
        Http::fake([self::TEST_ENDPOINT => Http::response($fault, 500)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_TRANSPORT_ERROR, $submission->status);
        $attempt = VerifactuSubmissionAttempt::where('verifactu_submission_id', $submission->id)->first();
        $this->assertEquals(VerifactuSubmissionAttempt::OUTCOME_SOAP_FAULT, $attempt->outcome);
    }

    /** @test */
    public function malformed_response_does_not_crash_the_job(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response('not xml at all', 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $submission->refresh();
        // A structurally invalid 200 response still resolves through the
        // parser's safe default (Incorrecto/rejected), not an unhandled
        // exception.
        $this->assertEquals(VerifactuSubmission::STATUS_REJECTED, $submission->status);
    }

    // ── Idempotency / races ──────────────────────────────────────

    /** @test */
    public function a_terminal_submission_is_never_resent(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto', 'CSV-ONCE'), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);
        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        Http::assertSentCount(1);
    }

    /** @test */
    public function a_submission_already_sending_is_not_claimed_again(): void
    {
        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);
        $submission->update(['status' => VerifactuSubmission::STATUS_SENDING]);

        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto'), 200)]);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        Http::assertNothingSent();
        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_SENDING, $submission->status);
    }

    // ── Flow control (art. 16.2) ───────────────────────────────

    /** @test */
    public function throttled_nif_releases_the_job_instead_of_sending(): void
    {
        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        VerifactuChainState::where('nif_emisor', self::NIF)->update([
            'next_submission_not_before' => now()->addSeconds(30),
        ]);

        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto'), 200)]);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        Http::assertNothingSent();
        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_PENDING, $submission->status);
    }

    /** @test */
    public function accepted_response_updates_the_chain_states_next_allowed_submission_time(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto', 'CSV', null, null, 90), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $chain = VerifactuChainState::where('nif_emisor', self::NIF)->first();
        $this->assertTrue($chain->next_submission_not_before->between(now()->addSeconds(85), now()->addSeconds(95)));
    }

    // ── Certificate preconditions ────────────────────────────────

    /** @test */
    public function missing_certificate_fails_the_job_permanently(): void
    {
        app(VerifactuCertificateService::class)->delete(self::NIF);
        Http::fake();

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        Http::assertNothingSent();
        $attempt = VerifactuSubmissionAttempt::where('verifactu_submission_id', $submission->id)->first();
        $this->assertEquals(VerifactuSubmissionAttempt::OUTCOME_TRANSPORT_ERROR, $attempt->outcome);
    }

    /** @test */
    public function expired_certificate_fails_the_job_without_sending(): void
    {
        \App\Models\Verifactu\VerifactuCertificate::where('nif', self::NIF)->update(['valid_to' => now()->subDay()]);
        Http::fake();

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        Http::assertNothingSent();
    }

    // ── Payload integrity ────────────────────────────────────────

    /** @test */
    public function submission_payload_matches_the_records_own_xml_builder_output(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto'), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $expectedXml = app(\App\Services\Verifactu\VerifactuXmlBuilder::class)->build($record->fresh(['taxDetails', 'previousRecord']));
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        $this->assertEquals($expectedXml, $submission->payload_xml);
        $this->assertEquals(hash('sha256', $expectedXml), $submission->payload_checksum);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        // The payload must be untouched by sending, even after a real
        // network round-trip.
        $submission->refresh();
        $this->assertEquals($expectedXml, $submission->payload_xml);
    }

    // ── SOAP envelope structure (item 7/11) ─────────────────────

    /** @test */
    public function envelope_wraps_exactly_one_registro_and_uses_the_correct_headers(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto'), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        Http::assertSent(function ($request) {
            $this->assertEquals('text/xml; charset=utf-8', $request->header('Content-Type')[0]);
            $this->assertEquals([''], $request->header('SOAPAction'));

            $dom = new \DOMDocument();
            $dom->loadXML($request->body());
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd');
            $xpath->registerNamespace('sum1', self::NS_SF);

            $registros = $xpath->query('//sum:RegistroFactura');
            $this->assertEquals(1, $registros->length, 'Exactly one RegistroFactura per submission - never a batch.');

            $nif = $xpath->query('//sum:Cabecera/sum1:ObligadoEmision/sum1:NIF')->item(0)->textContent;
            $this->assertEquals(self::NIF, $nif);

            $this->assertEquals(1, $xpath->query('//sum1:RegistroAlta')->length);

            return true;
        });
    }

    // ── Incidencia flag on retry (Phase 2D.1 finding) ───────────

    /** @test */
    public function first_attempt_does_not_set_incidencia(): void
    {
        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto'), 200)]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        Http::assertSent(function ($request) {
            $this->assertEquals(0, $this->queryEnvelope($request->body(), '//sum1:Incidencia')->length);

            return true;
        });
    }

    /** @test */
    public function retry_after_a_transport_failure_sets_incidencia_s(): void
    {
        $callCount          = 0;
        $secondRequestBody  = null;

        Http::fake([
            self::TEST_ENDPOINT => function ($request) use (&$callCount, &$secondRequestBody) {
                $callCount++;

                if ($callCount === 1) {
                    throw new ConnectionException('Connection timed out');
                }

                $secondRequestBody = $request->body();

                return Http::response($this->aeatXmlResponse('Correcto', 'Correcto'), 200);
            },
        ]);

        $record     = $this->issuedInvoiceWithRecord();
        $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

        // First attempt fails and marks retry_count=1.
        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);
        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_TRANSPORT_ERROR, $submission->status);
        $this->assertEquals(1, $submission->retry_count);

        // Second attempt (a real retry after a connectivity incident,
        // per AEAT's own FAQ - see AeatVerifactuClient::submit()) must
        // flag Incidencia=S.
        SendVerifactuRecordToAeatJob::dispatchSync($this->tenant, $submission->id);

        $this->assertEquals(2, $callCount);
        $this->assertNotNull($secondRequestBody);
        $incidencia = $this->queryEnvelope($secondRequestBody, '//sum1:Incidencia');
        $this->assertEquals(1, $incidencia->length);
        $this->assertEquals('S', $incidencia->item(0)->textContent);

        $submission->refresh();
        $this->assertEquals(VerifactuSubmission::STATUS_ACCEPTED, $submission->status);
    }

    private function queryEnvelope(string $xml, string $xpathQuery): \DOMNodeList
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd');
        $xpath->registerNamespace('sum1', self::NS_SF);

        return $xpath->query($xpathQuery);
    }

    // ── Tenant isolation ─────────────────────────────────────────

    /** @test */
    public function tenant_bs_job_never_sends_tenant_as_certificate_or_data(): void
    {
        $tenantB = Tenant::create(['id' => 'test-vfjob-b-' . uniqid()]);
        $tenantB->domains()->create(['domain' => 'test-vfjob-b-' . uniqid() . '.fakturalista.test']);

        Http::fake([self::TEST_ENDPOINT => Http::response($this->aeatXmlResponse('Correcto', 'Correcto'), 200)]);

        $tenantB->run(function () use ($tenantB) {
            CompanyProfile::create([
                'legal_name'                    => 'Otra Empresa SL',
                'tax_id'                        => '11111111H',
                'invoice_prefix'                => 'INV',
                'onboarding_completed_at'       => now(),
                'verifactu_installation_number' => 'TEST-INSTALL-2',
            ]);
            // Deliberately NO certificate uploaded for tenant B.

            $customer = Customer::factory()->create(['tax_id' => 'C22222222']);
            $invoice  = Invoice::create([
                'uuid' => Str::uuid()->toString(), 'reference' => 'DRAFT-B', 'customer_id' => $customer->id,
                'date' => now()->toDateString(), 'expiration_date' => now()->addDays(30)->toDateString(),
                'status' => Invoice::STATUS_DRAFT, 'sub_total' => 50, 'total' => 55, 'vta' => 5,
                'vta4' => 0, 'vta10' => 5, 'vta21' => 0, 'discount_rate' => 0, 'discount_amount' => 0,
                'descripcion_operacion' => 'Otro',
            ]);
            $companyB = CompanyProfile::first();
            app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $companyB);
            $invoice->status = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->save();

            $recordB = (new VerifactuChainService(new VerifactuHashService()))->recordAlta($invoice, $companyB);
            $submissionB = app(VerifactuSubmissionService::class)->createSubmission($recordB);

            SendVerifactuRecordToAeatJob::dispatchSync($tenantB, $submissionB->id);

            // No certificate exists for tenant B - must fail closed, never
            // fall back to tenant A's (this test's own) certificate.
            Http::assertNothingSent();
        });

        $tenantB->delete();
    }
}
