<?php

namespace App\Models\Verifactu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks the last hash produced for a given emitting NIF, so
 * VerifactuChainService knows what to chain the next record to. See the
 * migration docblock for why this is keyed by nif_emisor rather than
 * assumed 1:1 with the tenant.
 */
class VerifactuChainState extends Model
{
    protected $table = 'verifactu_chain_states';

    protected $fillable = [
        'nif_emisor',
        'last_huella',
        'last_verifactu_record_id',
        'next_submission_not_before',
    ];

    protected $casts = [
        'next_submission_not_before' => 'datetime',
    ];

    public function lastRecord(): BelongsTo
    {
        return $this->belongsTo(VerifactuRecord::class, 'last_verifactu_record_id');
    }

    /**
     * Orden HAC/1177/2024 art. 16.2 flow control - true until this NIF's
     * next allowed submission time has arrived. See
     * docs/verifactu-aeat-connectivity.md §10.
     */
    public function isThrottled(): bool
    {
        return $this->next_submission_not_before !== null
            && $this->next_submission_not_before->isFuture();
    }
}
