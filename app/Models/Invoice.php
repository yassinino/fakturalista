<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    // AEAT ClaveTipoFacturaType (Orden HAC/1177/2024, anexo). Fakturalista
    // currently only issues complete invoices by default (TYPE_F1); the
    // others exist so the rectification workflow and future simplified-
    // invoice support have a real column to write to.
    const TYPE_F1 = 'F1'; // Factura completa
    const TYPE_F2 = 'F2'; // Factura simplificada
    const TYPE_F3 = 'F3'; // Factura de sustitución de una simplificada
    const TYPE_R1 = 'R1'; // Rectificativa - error fundado en derecho (LIVA art. 80.1/80.2/80.6)
    const TYPE_R2 = 'R2'; // Rectificativa - concurso de acreedores (LIVA art. 80.3)
    const TYPE_R3 = 'R3'; // Rectificativa - créditos incobrables (LIVA art. 80.4)
    const TYPE_R4 = 'R4'; // Rectificativa - otras causas del art. 80 LIVA
    const TYPE_R5 = 'R5'; // Rectificativa de una factura simplificada

    const RECTIFICATION_TYPES = ['R1', 'R2', 'R3', 'R4', 'R5'];

    // RD 1619/2012 art. 15.5
    const RECTIFICATION_MODE_SUSTITUCION = 'S';
    const RECTIFICATION_MODE_DIFERENCIAS = 'I';

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
        'invoice_series',
        'invoice_number',
        'invoice_type',
        'rectification_type',
        'rectifies_invoice_id',
        'rectification_reason',
        'cancellation_reason',
        'descripcion_operacion',
        // Morocco Phase 1B (docs/morocco-phase-1b-identity.md §7) - generic,
        // country-agnostic snapshot of seller/customer identity at the
        // moment of issuance, so an issued invoice never depends on a
        // later edit to CompanyProfile/Customer. Written once, in
        // InvoiceController::issueInvoice(); never touched again. Not
        // VERI*FACTU's own separate snapshot (verifactu_records) - this
        // exists for every tenant regardless of country.
        'company_snapshot',
        'customer_snapshot',
    ];

    protected $casts = [
        'issued_at'                 => 'datetime',
        'stripe_session_expires_at' => 'datetime',
        'paid_at'                   => 'datetime',
        'invoice_number'            => 'integer',
        'company_snapshot'          => 'array',
        'customer_snapshot'         => 'array',
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

    /**
     * Generic, country-neutral tax breakdown - Morocco Phase 1C.1 (docs/
     * morocco-phase-1c1-generic-tax-foundation.md). Empty for any invoice
     * issued before this phase existed - see InvoiceTaxLine's docblock.
     */
    public function taxLines(): HasMany
    {
        return $this->hasMany(InvoiceTaxLine::class)->orderBy('rate');
    }

    /**
     * The original invoice this one rectifies (null unless this invoice
     * is itself a rectificativa).
     */
    public function rectifies(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'rectifies_invoice_id');
    }

    /**
     * Rectificative invoices issued against this one.
     */
    public function rectifications(): HasMany
    {
        return $this->hasMany(Invoice::class, 'rectifies_invoice_id');
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

    public function isRectification(): bool
    {
        return !is_null($this->rectifies_invoice_id);
    }

    public function hasRectifications(): bool
    {
        return $this->rectifications()->exists();
    }

    /**
     * Has this invoice been legally issued (has a definitive series/number)?
     * Distinct from isLocked(): a directly-created test/legacy row can have
     * status=issued without ever going through the numbering service, so
     * this checks the actual regulatory state, not just the status flag.
     */
    public function hasLegalNumber(): bool
    {
        return !is_null($this->invoice_series) && !is_null($this->invoice_number);
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
