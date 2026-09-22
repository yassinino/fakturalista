<?php

namespace Tests\Feature;

use App\Exceptions\Verifactu\VerifactuCertificateException;
use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verifactu\VerifactuCertificate;
use App\Services\Verifactu\Auth\CustomerCertificateProvider;
use App\Services\Verifactu\VerifactuCertificateService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 2D §3/§4/§11. All certificates here are freshly-generated,
 * fictional, self-signed test material created in-process via PHP's own
 * openssl_* functions - never a committed file, never a real taxpayer's
 * certificate.
 */
class VerifactuCertificateTest extends TestCase
{
    protected Tenant $tenant;
    protected User $user;
    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'test-vfcert-' . uniqid()]);
        $this->domain = 'test-vfcert-' . uniqid() . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);

        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();

        CompanyProfile::create([
            'legal_name'              => 'Empresa Ficticia de Pruebas SL',
            'tax_id'                  => '89890001K',
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function apiUrl(string $path): string
    {
        return 'http://' . $this->domain . $path;
    }

    /**
     * Generates a fresh, fictional, self-signed PKCS#12 in-process -
     * never a file on disk, never a real identity.
     */
    private function makeTestPkcs12(string $commonName = 'FICTICIO TEST', int $validDays = 365): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        $csr = openssl_csr_new(
            ['commonName' => $commonName, 'countryName' => 'ES'],
            $key,
            ['digest_alg' => 'sha256']
        );

        $cert = openssl_csr_sign($csr, null, $key, $validDays, ['digest_alg' => 'sha256']);

        $passphrase = 'test-pass-' . uniqid();
        openssl_pkcs12_export($cert, $pkcs12, $key, $passphrase);

        return ['pkcs12' => $pkcs12, 'passphrase' => $passphrase, 'commonName' => $commonName];
    }

    // ── Upload / storage ─────────────────────────────────────

    /** @test */
    public function uploading_a_valid_certificate_extracts_safe_metadata(): void
    {
        $fixture = $this->makeTestPkcs12('Carlos Autonomo TEST');

        $certificate = app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

        $this->assertStringContainsString('Carlos Autonomo TEST', $certificate->subject);
        $this->assertNotNull($certificate->valid_from);
        $this->assertNotNull($certificate->valid_to);
        $this->assertFalse($certificate->isExpired());
    }

    /** @test */
    public function certificate_bytes_are_never_stored_in_plaintext_on_disk(): void
    {
        $fixture = $this->makeTestPkcs12();
        $certificate = app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

        $onDisk = Storage::disk('local')->get($certificate->encrypted_file_path);

        $this->assertNotEquals($fixture['pkcs12'], $onDisk, 'The raw PKCS#12 bytes must never be written unencrypted.');
        // But it IS recoverable via Crypt - proving it's genuinely
        // encrypted, not merely different by accident.
        $this->assertEquals($fixture['pkcs12'], Crypt::decryptString($onDisk));
    }

    /** @test */
    public function passphrase_is_encrypted_at_rest_as_a_separate_secret(): void
    {
        $fixture = $this->makeTestPkcs12();
        $certificate = app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

        $raw = \Illuminate\Support\Facades\DB::table('verifactu_certificates')->where('id', $certificate->id)->first();

        $this->assertNotEquals($fixture['passphrase'], $raw->passphrase);
    }

    /** @test */
    public function certificate_and_passphrase_are_hidden_from_array_serialization(): void
    {
        $fixture = $this->makeTestPkcs12();
        $certificate = app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

        $array = $certificate->toArray();

        $this->assertArrayNotHasKey('passphrase', $array);
        $this->assertArrayNotHasKey('encrypted_file_path', $array);
    }

    /** @test */
    public function invalid_pkcs12_file_is_rejected_with_a_safe_message(): void
    {
        $this->expectException(VerifactuCertificateException::class);

        app(VerifactuCertificateService::class)->upload('89890001K', 'not a real pkcs12 file', 'whatever');
    }

    /** @test */
    public function wrong_passphrase_is_rejected(): void
    {
        $fixture = $this->makeTestPkcs12();

        $this->expectException(VerifactuCertificateException::class);

        app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], 'wrong-passphrase');
    }

    /** @test */
    public function replacing_a_certificate_leaves_no_trace_of_the_old_encrypted_content(): void
    {
        $service      = app(VerifactuCertificateService::class);
        $firstFixture = $this->makeTestPkcs12('Primer Certificado TEST');
        $first        = $service->upload('89890001K', $firstFixture['pkcs12'], $firstFixture['passphrase']);

        $second     = $this->makeTestPkcs12('Segundo Certificado TEST');
        $replaced   = $service->upload('89890001K', $second['pkcs12'], $second['passphrase']);

        // Deterministic per-NIF path, so replacement overwrites in place -
        // the important guarantee is that the OLD encrypted bytes are
        // gone, not the path itself.
        $onDisk = Storage::disk('local')->get($replaced->encrypted_file_path);
        $this->assertEquals($second['pkcs12'], Crypt::decryptString($onDisk));
        $this->assertNotEquals($firstFixture['pkcs12'], Crypt::decryptString($onDisk));

        $this->assertEquals(1, VerifactuCertificate::where('nif', '89890001K')->count(), 'Replacing must update the existing row, not create a second one.');
        $this->assertStringContainsString('Segundo Certificado TEST', $replaced->subject);
    }

    /** @test */
    public function deleting_a_certificate_removes_the_encrypted_file(): void
    {
        $fixture     = $this->makeTestPkcs12();
        $certificate = app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);
        $path        = $certificate->encrypted_file_path;

        app(VerifactuCertificateService::class)->delete('89890001K');

        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertDatabaseMissing('verifactu_certificates', ['nif' => '89890001K']);
    }

    // ── Resolution / expiry ────────────────────────────────────

    /** @test */
    public function customer_certificate_provider_decrypts_and_returns_usable_material(): void
    {
        $fixture = $this->makeTestPkcs12();
        app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

        $certificate = app(CustomerCertificateProvider::class)->resolveCertificate('89890001K');
        $path = $certificate->writeTemporaryPemFile();

        $this->assertFileExists($path);
        $this->assertStringContainsString('BEGIN CERTIFICATE', file_get_contents($path));
        $this->assertStringContainsString('PRIVATE KEY', file_get_contents($path));
        @unlink($path);
    }

    /** @test */
    public function missing_certificate_throws_a_clear_exception(): void
    {
        $this->expectException(VerifactuCertificateException::class);

        app(CustomerCertificateProvider::class)->resolveCertificate('00000000X');
    }

    /** @test */
    public function expired_certificate_is_refused_even_if_the_file_is_present(): void
    {
        // validDays=0 backdated via direct manipulation - openssl won't
        // issue a cert already expired, so we upload a normal one and
        // then flip its own recorded valid_to into the past.
        $fixture = $this->makeTestPkcs12();
        app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

        VerifactuCertificate::where('nif', '89890001K')->update(['valid_to' => now()->subDay()]);

        $this->expectException(VerifactuCertificateException::class);

        app(CustomerCertificateProvider::class)->resolveCertificate('89890001K');
    }

    // ── HTTP settings endpoint ───────────────────────────────

    /** @test */
    public function settings_endpoint_reports_not_configured_when_no_certificate_exists(): void
    {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson($this->apiUrl('/api/settings/verifactu/certificate'));

        $response->assertStatus(200)
                 ->assertJsonPath('configured', false)
                 ->assertJsonPath('environment', 'test');
    }

    /** @test */
    public function settings_endpoint_never_exposes_the_private_key_or_passphrase(): void
    {
        $fixture = $this->makeTestPkcs12();
        app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);
        $this->actingAs($this->user, 'api');

        $response = $this->getJson($this->apiUrl('/api/settings/verifactu/certificate'));

        $response->assertStatus(200)->assertJsonPath('configured', true);
        $body = $response->json();
        $this->assertArrayNotHasKey('passphrase', $body['certificate']);
        $this->assertArrayNotHasKey('encrypted_file_path', $body['certificate']);
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode($body));
        $this->assertStringNotContainsString($fixture['passphrase'], json_encode($body));
    }

    // ── Tenant isolation (item 11) ───────────────────────────

    /** @test */
    public function tenant_a_certificate_cannot_resolve_for_tenant_bs_nif(): void
    {
        $fixture = $this->makeTestPkcs12();
        app(VerifactuCertificateService::class)->upload('89890001K', $fixture['pkcs12'], $fixture['passphrase']);

        $tenantB = Tenant::create(['id' => 'test-vfcert-b-' . uniqid()]);
        $tenantB->domains()->create(['domain' => 'test-vfcert-b-' . uniqid() . '.fakturalista.test']);

        $resolvedInB = $tenantB->run(function () {
            try {
                app(CustomerCertificateProvider::class)->resolveCertificate('89890001K');

                return 'resolved';
            } catch (VerifactuCertificateException $e) {
                return 'not_found';
            }
        });

        $this->assertEquals(
            'not_found',
            $resolvedInB,
            'Tenant B must never resolve a certificate that only exists in Tenant A\'s own database.'
        );

        $tenantB->delete();
    }
}
