<?php

namespace App\Models\Verifactu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifactuSubmissionAttempt extends Model
{
    public const OUTCOME_SUCCESS          = 'success';
    public const OUTCOME_TRANSPORT_ERROR  = 'transport_error';
    public const OUTCOME_TLS_ERROR        = 'tls_error';
    public const OUTCOME_SOAP_FAULT       = 'soap_fault';
    public const OUTCOME_TIMEOUT          = 'timeout';

    protected $table = 'verifactu_submission_attempts';

    protected $fillable = [
        'verifactu_submission_id',
        'attempt_number',
        'started_at',
        'finished_at',
        'outcome',
        'http_status',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VerifactuSubmission::class, 'verifactu_submission_id');
    }
}
