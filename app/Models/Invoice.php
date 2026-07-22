<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT     = 'draft';
    const STATUS_ISSUED    = 'issued';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    // Statuses that prevent direct editing
    const LOCKED_STATUSES = [self::STATUS_ISSUED, self::STATUS_PAID, self::STATUS_CANCELLED];

    protected $fillable = [
        'customer_id',
        'reference',
        'uuid',
        'ice',
        'date',
        'status',
        'issued_at',
        'source_invoice_id',
        'expiration_date',
        'payment_terms',
        'sub_total',
        'discount_rate',
        'discount_amount',
        'vta',
        'vta4',
        'vta10',
        'vta21',
        'total',
        'note',
        'stripe_session_id',
        'stripe_payment_url',
        'stripe_session_expires_at',
        'paid_at',
        'paid_via',
    ];

    protected $casts = [
        'issued_at'                 => 'datetime',
        'stripe_session_expires_at' => 'datetime',
        'paid_at'                   => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ── Relationships ──────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function carts(): MorphMany
    {
        return $this->morphMany(Cart::class, 'cartable');
    }

    public function history(): HasMany
    {
        return $this->hasMany(InvoiceHistory::class)->orderBy('created_at', 'asc');
    }

    // ── Status helpers ─────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::LOCKED_STATUSES);
    }

    // ── Audit helper ───────────────────────────────────────

    public function logHistory(string $action, array $context = []): void
    {
        $this->history()->create([
            'action'  => $action,
            'context' => empty($context) ? null : $context,
        ]);
    }
}
