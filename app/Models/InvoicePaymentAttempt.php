<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Stripe Checkout Session started from the Client Portal for an
 * invoice (Step 6A). Only App\Services\ClientPortal\PortalInvoicePaymentService
 * creates or updates these. Never related to the central SaaS `payments`
 * table (Fakturalista's own subscription billing).
 */
class InvoicePaymentAttempt extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_OPEN     = 'open';
    public const STATUS_PAID     = 'paid';
    public const STATUS_EXPIRED  = 'expired';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid',
        'invoice_id',
        'provider',
        'purpose',
        'stripe_account_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'checkout_url',
        'amount_minor',
        'currency',
        'status',
        'failure_reason',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'expires_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
