<?php

namespace App\Models\Verifactu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Network/submission state for one VerifactuRecord - never the fiscal
 * record itself. See docs/verifactu-aeat-connectivity.md §5.
 */
class VerifactuSubmission extends Model
{
    public const STATUS_PENDING              = 'pending';
    public const STATUS_SENDING              = 'sending';
    public const STATUS_ACCEPTED             = 'accepted';
    public const STATUS_ACCEPTED_WITH_ERRORS = 'accepted_with_errors';
    public const STATUS_REJECTED             = 'rejected';
    public const STATUS_TRANSPORT_ERROR      = 'transport_error';

    public const TERMINAL_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_ACCEPTED_WITH_ERRORS,
        self::STATUS_REJECTED,
    ];

    protected $table = 'verifactu_submissions';

    protected $fillable = [
        'verifactu_record_id',
        'nif',
        'environment',
        'payload_xml',
        'payload_checksum',
        'status',
        'http_status',
        'aeat_estado_envio',
        'aeat_estado_registro',
        'aeat_error_code',
        'aeat_error_description',
        'csv',
        'duplicate_of_id_peticion',
        'response_raw',
        'submitted_at',
        'response_received_at',
        'completed_at',
        'retry_count',
        'last_error',
    ];

    protected $casts = [
        'submitted_at'          => 'datetime',
        'response_received_at'  => 'datetime',
        'completed_at'          => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(VerifactuRecord::class, 'verifactu_record_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(VerifactuSubmissionAttempt::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isSendable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_TRANSPORT_ERROR], true);
    }
}
