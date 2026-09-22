<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the generic, country-neutral tax breakdown for an invoice -
 * Morocco Phase 1C.1 (docs/morocco-phase-1c1-generic-tax-foundation.md).
 * Written by DocumentCalculationService via InvoiceController/
 * QuoteToInvoiceService. Never written to directly for a locked
 * (issued/paid/cancelled) invoice - by the time an invoice is locked,
 * nothing calls the write path that touches this table, which is what
 * makes an issued invoice's breakdown immutable in practice.
 */
class InvoiceTaxLine extends Model
{
    protected $table = 'invoice_tax_lines';

    protected $fillable = [
        'invoice_id',
        'rate',
        // Morocco Phase 1C.2 - taxable/exempt/out_of_scope, see
        // App\Services\Tax\TaxTreatment. Part of the grouping key
        // alongside `rate` - an exempt 0% row and a taxable 0% row are
        // never merged.
        'treatment',
        'taxable_base',
        'tax_amount',
    ];

    protected $casts = [
        'rate'         => 'float',
        'taxable_base' => 'float',
        'tax_amount'   => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
