<?php

namespace App\Jobs;

use App\Exceptions\Verifactu\AeatSoapFaultException;
use App\Exceptions\Verifactu\AeatTlsException;
use App\Exceptions\Verifactu\AeatTransportException;
use App\Exceptions\Verifactu\VerifactuCertificateException;
use App\Models\CompanyProfile;
use App\Models\Verifactu\VerifactuChainState;
use App\Models\Verifactu\VerifactuSubmission;
use App\Models\Verifactu\VerifactuSubmissionAttempt;
use App\Services\Verifactu\Aeat\AeatEndpointResolver;
use App\Services\Verifactu\Aeat\AeatResponseParser;
use App\Services\Verifactu\Aeat\AeatVerifactuClient;
use App\Services\Verifactu\Auth\AeatAuthenticationProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Sends one already-created VerifactuSubmission to AEAT's TEST endpoint.
 * Never touches verifactu_records - only submission/attempt rows. See
 * docs/verifactu-aeat-connectivity.md §9/§10.
 *
 * The job payload is only a tenant reference + a submission ID -
 * NEVER a certificate or decrypted key material (queue payloads are
 * serialized into the queue backend's own storage). The certificate is
 * re-resolved fresh, inside the correct tenant context, every time this
 * job actually runs - matching the established tenant-job pattern in
 * app/Jobs/SeedTenantCountries.php.
 */
class SendVerifactuRecordToAeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        protected TenantWithDatabase $tenant,
        protected int $verifactuSubmissionId,
    ) {
    }

    public function handle(): void
    {
        tenancy()->runForMultiple([$this->tenant->getTenantKey()], function () {
            $this->process();
        });
    }

    private function process(): void
    {
        $submission = VerifactuSubmission::find($this->verifactuSubmissionId);

        if (!$submission) {
            Log::channel('verifactu')->warning('verifactu.submission.not_found', [
                'submission_id' => $this->verifactuSubmissionId,
            ]);

            return;
        }

        // Orden HAC/1177/2024 art. 16.2 flow control - never send before
        // this NIF's next allowed submission time.
        $chain = VerifactuChainState::where('nif_emisor', $submission->nif)->first();
        if ($chain && $chain->isThrottled()) {
            $this->release(now()->diffInSeconds($chain->next_submission_not_before, true) + 1);

            return;
        }

        // Idempotency: lock the row, bail if another worker already
        // claimed or finished it.
        $claimed = DB::transaction(function () use ($submission) {
            $locked = VerifactuSubmission::where('id', $submission->id)->lockForUpdate()->first();

            if (!$locked || !$locked->isSendable()) {
                return null;
            }

            $locked->status       = VerifactuSubmission::STATUS_SENDING;
            $locked->submitted_at = $locked->submitted_at ?? now();
            $locked->save();

            return $locked;
        });

        if (!$claimed) {
            return;
        }

        $company        = CompanyProfile::first();
        $attemptNumber  = $claimed->attempts()->count() + 1;
        $startedAt      = now();

        try {
            $client = new AeatVerifactuClient(
                app(AeatEndpointResolver::class),
                app(AeatAuthenticationProvider::class),
                app(AeatResponseParser::class),
            );

            $result = $client->submit($claimed, $company->tax_id, $company->legal_name);

            $this->recordAttempt($claimed, $attemptNumber, $startedAt, VerifactuSubmissionAttempt::OUTCOME_SUCCESS);
            $this->applyResult($claimed, $result, $chain);
        } catch (VerifactuCertificateException $e) {
            $this->recordAttempt($claimed, $attemptNumber, $startedAt, VerifactuSubmissionAttempt::OUTCOME_TRANSPORT_ERROR, $e->getMessage());
            $this->markTransportError($claimed, $e->getMessage());
            $this->fail($e);
        } catch (AeatTlsException $e) {
            $this->recordAttempt($claimed, $attemptNumber, $startedAt, VerifactuSubmissionAttempt::OUTCOME_TLS_ERROR, $e->getMessage());
            $this->markTransportError($claimed, $e->getMessage());
        } catch (AeatSoapFaultException $e) {
            $this->recordAttempt($claimed, $attemptNumber, $startedAt, VerifactuSubmissionAttempt::OUTCOME_SOAP_FAULT, $e->getMessage());
            $this->markTransportError($claimed, $e->getMessage());
        } catch (AeatTransportException $e) {
            $this->recordAttempt($claimed, $attemptNumber, $startedAt, VerifactuSubmissionAttempt::OUTCOME_TRANSPORT_ERROR, $e->getMessage());
            $this->markTransportError($claimed, $e->getMessage());
            $this->release($this->backoffSeconds($attemptNumber));
        }
    }

    private function applyResult(VerifactuSubmission $submission, \App\Services\Verifactu\Aeat\AeatSubmissionResult $result, ?VerifactuChainState $chain): void
    {
        // AEAT is idempotent on the natural key - a duplicate rejection
        // adopts the original's outcome rather than being treated as a
        // failure. See docs/verifactu-aeat-connectivity.md §10.
        $status = $result->isDuplicate() ? VerifactuSubmission::STATUS_ACCEPTED : $result->mappedStatus();

        $submission->status                  = $status;
        $submission->aeat_estado_envio        = $result->estadoEnvio;
        $submission->aeat_estado_registro     = $result->estadoRegistro;
        $submission->aeat_error_code          = $result->errorCode;
        $submission->aeat_error_description   = $result->errorDescription;
        $submission->csv                      = $result->csv;
        $submission->duplicate_of_id_peticion = $result->duplicateOfIdPeticion;
        $submission->response_raw             = $result->rawResponseXml;
        $submission->response_received_at     = now();
        $submission->completed_at             = now();
        $submission->save();

        if ($chain) {
            $waitSeconds = $result->tiempoEsperaEnvio ?? (int) config('verifactu.aeat.default_flow_control_seconds');
            $chain->next_submission_not_before = now()->addSeconds($waitSeconds);
            $chain->save();
        }

        Log::channel('verifactu')->info('verifactu.submission.completed', [
            'submission_id' => $submission->id,
            'status'        => $status,
            'estado_envio'  => $result->estadoEnvio,
        ]);
    }

    private function markTransportError(VerifactuSubmission $submission, string $message): void
    {
        $submission->status      = VerifactuSubmission::STATUS_TRANSPORT_ERROR;
        $submission->retry_count = $submission->retry_count + 1;
        $submission->last_error  = $message;
        $submission->save();
    }

    private function recordAttempt(
        VerifactuSubmission $submission,
        int $attemptNumber,
        \Illuminate\Support\Carbon $startedAt,
        string $outcome,
        ?string $errorMessage = null
    ): void {
        $durationMs = $startedAt->diffInMilliseconds(now());

        VerifactuSubmissionAttempt::create([
            'verifactu_submission_id' => $submission->id,
            'attempt_number'          => $attemptNumber,
            'started_at'              => $startedAt,
            'finished_at'             => now(),
            'outcome'                 => $outcome,
            'error_message'           => $errorMessage,
            'duration_ms'             => $durationMs,
        ]);

        // Structured, secret-free observability - see
        // docs/verifactu-aeat-connectivity.md §15. Never log certificate/
        // key/passphrase content or full response bodies here.
        Log::channel('verifactu')->info('verifactu.submission.attempt', [
            'tenant_id'      => $this->tenant->getTenantKey(),
            'submission_id'  => $submission->id,
            'record_id'      => $submission->verifactu_record_id,
            'environment'    => $submission->environment,
            'attempt_number' => $attemptNumber,
            'outcome'        => $outcome,
            'duration_ms'    => $durationMs,
        ]);
    }

    private function backoffSeconds(int $attemptNumber): int
    {
        // Engineering judgment, not a regulatory value (unlike the art.
        // 16.2 flow-control wait, which lives on VerifactuChainState).
        return min(30 * (2 ** ($attemptNumber - 1)), 7200);
    }
}
