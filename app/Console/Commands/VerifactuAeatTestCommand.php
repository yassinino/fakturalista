<?php

namespace App\Console\Commands;

use App\Exceptions\Verifactu\VerifactuCertificateException;
use App\Jobs\SendVerifactuRecordToAeatJob;
use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Models\Verifactu\VerifactuCertificate;
use App\Models\Verifactu\VerifactuRecord;
use App\Models\Verifactu\VerifactuSubmission;
use App\Services\Verifactu\Aeat\AeatEndpointResolver;
use App\Services\Verifactu\Auth\AeatAuthenticationProvider;
use App\Services\Verifactu\VerifactuSubmissionService;
use App\Services\Verifactu\VerifactuXmlBuilder;
use App\Services\Verifactu\VerifactuXmlValidator;
use Illuminate\Console\Command;

/**
 * Manual, opt-in integration test against AEAT's real external TEST
 * service (docs/verifactu-aeat-connectivity.md §17). NEVER runs during
 * PHPUnit - only invoked explicitly by a developer.
 *
 * Documented workflow (§13 of the connectivity doc):
 *   1. Create a fictitious tenant/customer/invoice and issue it (never
 *      real customer data).
 *   2. Generate its VerifactuRecord via VerifactuChainService (Phase 2C).
 *   3. Upload a real AEAT-test-environment certificate for that tenant's
 *      NIF via Settings -> VERI*FACTU (or VerifactuCertificateService
 *      directly in tinker).
 *   4. Run this command with that record's ID.
 */
class VerifactuAeatTestCommand extends Command
{
    protected $signature = 'verifactu:aeat-test
        {tenant : Tenant ID to run this in}
        {--record= : ID of an existing VerifactuRecord to submit}
        {--force : Skip the interactive confirmation (still refuses anything but the test environment)}';

    protected $description = 'Send ONE VerifactuRecord to the official AEAT external TEST web service. Never touches production.';

    public function handle(AeatEndpointResolver $endpoints, AeatAuthenticationProvider $auth, VerifactuXmlBuilder $xmlBuilder, VerifactuXmlValidator $xmlValidator): int
    {
        // Refuse anything but 'test', independent of AeatEndpointResolver's
        // own guard - belt and braces for the one command that can
        // actually reach the network.
        if ($endpoints->environment() !== 'test') {
            $this->error("Entorno no soportado: '{$endpoints->environment()}'. Este comando solo funciona en 'test'.");

            return self::FAILURE;
        }

        $tenant = Tenant::find($this->argument('tenant'));
        if (!$tenant) {
            $this->error('Tenant no encontrado: ' . $this->argument('tenant'));

            return self::FAILURE;
        }

        $recordId = $this->option('record');
        if (!$recordId) {
            $this->error('Debes indicar --record=<id> con un VerifactuRecord existente. Este comando no crea datos ficticios por ti - ver docs/verifactu-aeat-connectivity.md §13.');

            return self::FAILURE;
        }

        $result = null;

        $tenant->run(function () use ($tenant, $recordId, $endpoints, $auth, $xmlBuilder, $xmlValidator, &$result) {
            $record = VerifactuRecord::find($recordId);
            if (!$record) {
                $this->error("VerifactuRecord #{$recordId} no existe en el tenant {$tenant->getTenantKey()}.");
                $result = self::FAILURE;

                return;
            }

            $company = CompanyProfile::first();
            if (empty($company?->tax_id)) {
                $this->error('Esta empresa no tiene NIF/CIF configurado. No se puede identificar al obligado de emisión.');
                $result = self::FAILURE;

                return;
            }

            $certificate = VerifactuCertificate::where('nif', trim($company->tax_id))->first();
            if (!$certificate) {
                $this->error('No hay ningún certificado VERI*FACTU configurado para este NIF (Settings -> VERI*FACTU).');
                $result = self::FAILURE;

                return;
            }

            // Reuse the exact same resolution path the job/SOAP client
            // will use (CustomerCertificateProvider) so "missing",
            // "expired" and "invalid/undecryptable" certificate all fail
            // here, before anything is shown or sent, with the same
            // message the job would otherwise only surface after a
            // wasted attempt.
            try {
                $auth->resolveCertificate($certificate->nif);
            } catch (VerifactuCertificateException $e) {
                $this->error($e->getMessage());
                $result = self::FAILURE;

                return;
            }

            // Build and XSD-validate the XML up front too, rather than
            // discovering an incomplete/unsupported invoice scenario (or
            // a schema mismatch) only after dispatching the job. Built
            // here only to validate - not yet persisted as a
            // VerifactuSubmission, so a "no" at the confirmation prompt
            // below leaves no dangling row.
            try {
                $previewXml = $xmlBuilder->build($record);
                $xmlValidator->validate($previewXml);
            } catch (\Throwable $e) {
                $this->error('No se pudo generar/validar el XML VERI*FACTU para este registro: ' . $e->getMessage());
                $result = self::FAILURE;

                return;
            }

            $this->line('');
            $this->line('<fg=yellow;options=bold>ENTORNO: AEAT PRUEBAS (nunca producción)</>');
            $this->line("Tenant: {$tenant->getTenantKey()}");
            $this->line("Contribuyente (NIF): {$company->legal_name} ({$company->tax_id})");
            $this->line("Factura/registro: {$record->serie_numero} ({$record->tipo_registro})");
            $this->line('Endpoint: ' . $endpoints->endpoint());
            $this->line("Sujeto del certificado: {$certificate->subject}");
            $this->line('Caducidad del certificado: ' . ($certificate->valid_to?->toDateString() ?? 'desconocida'));
            $this->line('');

            if (!$this->option('force') && !$this->confirm('¿Confirmas el envío REAL de este registro al entorno de PRUEBAS de la AEAT?')) {
                $this->warn('Cancelado.');
                $result = self::SUCCESS;

                return;
            }

            $submission = app(VerifactuSubmissionService::class)->createSubmission($record);

            // dispatchSync() runs the job's full logic (locking, flow
            // control, attempt recording, response parsing) inline, so
            // this command can wait for and print the outcome - reusing
            // the exact same code path a queued dispatch would use.
            SendVerifactuRecordToAeatJob::dispatchSync($tenant, $submission->id);

            $submission->refresh();

            $this->line('');
            $this->info('Resultado:');
            $this->line('  Estado: ' . $submission->status);
            $this->line('  EstadoEnvio (AEAT): ' . ($submission->aeat_estado_envio ?? '-'));
            $this->line('  EstadoRegistro (AEAT): ' . ($submission->aeat_estado_registro ?? '-'));
            $this->line('  CSV: ' . ($submission->csv ?? '-'));
            if ($submission->aeat_error_code) {
                $this->line('  Error AEAT: [' . $submission->aeat_error_code . '] ' . $submission->aeat_error_description);
            }
            if ($submission->last_error) {
                $this->line('  Último error de transporte: ' . $submission->last_error);
            }

            $result = $submission->status === VerifactuSubmission::STATUS_TRANSPORT_ERROR ? self::FAILURE : self::SUCCESS;
        });

        return $result ?? self::FAILURE;
    }
}
