<?php

namespace App\Services\Verifactu;

use App\Jobs\SendVerifactuRecordToAeatJob;
use App\Models\Verifactu\VerifactuRecord;
use App\Models\Verifactu\VerifactuSubmission;
use App\Services\Verifactu\Aeat\AeatEndpointResolver;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Creates a VerifactuSubmission from an already-generated VerifactuRecord
 * and queues it for delivery. NOT called anywhere from live invoice
 * issuance yet - see docs/verifactu-aeat-connectivity.md §19/§9. Used
 * today only by the manual `verifactu:aeat-test` command (§17).
 */
class VerifactuSubmissionService
{
    public function __construct(
        private VerifactuXmlBuilder $xmlBuilder,
        private AeatEndpointResolver $endpoints,
    ) {
    }

    public function createSubmission(VerifactuRecord $record): VerifactuSubmission
    {
        $xml = $this->xmlBuilder->build($record);

        return VerifactuSubmission::create([
            'verifactu_record_id' => $record->id,
            'nif'                 => $record->nif_emisor,
            'environment'         => $this->endpoints->environment(),
            'payload_xml'         => $xml,
            'payload_checksum'    => hash('sha256', $xml),
            'status'              => VerifactuSubmission::STATUS_PENDING,
        ]);
    }

    public function queue(VerifactuSubmission $submission, TenantWithDatabase $tenant): void
    {
        SendVerifactuRecordToAeatJob::dispatch($tenant, $submission->id);
    }
}
