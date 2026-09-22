<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoiceTaxLine;
use App\Models\Quote;
use App\Services\InvoiceNumberingService;
use App\Services\Tax\DocumentCalculationService;
use App\Services\Tax\TaxTreatment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuoteToInvoiceService
{
    public function __construct(
        private InvoiceNumberingService $numbering,
        private DocumentCalculationService $calculator,
    ) {}

    /**
     * Convert a quote into a draft invoice.
     *
     * Runs inside a DB transaction. Throws \RuntimeException for
     * validation failures (422-worthy) and allows other exceptions
     * to bubble so the caller can return a 500.
     *
     * @throws \RuntimeException when the quote is already converted.
     */
    public function convert(Quote $quote): Invoice
    {
        if ($quote->status === Quote::STATUS_CONVERTED) {
            throw new \RuntimeException('Este presupuesto ya ha sido convertido en factura.');
        }

        Log::info('Quote conversion started', [
            'quote_uuid'      => $quote->uuid,
            'quote_reference' => $quote->reference,
        ]);

        $invoice = DB::transaction(function () use ($quote) {
            $quote->load('carts');

            // Morocco Phase 1C.1 (docs/morocco-phase-1c1-generic-tax-foundation.md
            // §4) - the same authoritative calculator invoices/quotes
            // already use, run over the quote's OWN cart lines at
            // whatever rate each one actually is. This is the fix for
            // the Phase 1C audit's flagged bug: the old calcVta(4|10|21)
            // closure silently dropped any line whose rate wasn't
            // exactly one of those three (e.g. a future Moroccan 20%
            // line) from the invoice's tax breakdown entirely. The new
            // engine has no such whitelist - every rate present on the
            // quote survives conversion, in $taxBreakdown/invoice_tax_lines,
            // whether or not it happens to also have a legacy vta4/10/21
            // column to bridge into.
            $lines = $quote->carts->map(fn (Cart $cart) => [
                'quantity'   => $cart->qty,
                'unit_price' => $cart->price,
                'discount'   => $cart->discount,
                'tax_rate'   => $cart->vta,
                'treatment'  => $cart->tax_treatment ?? TaxTreatment::TAXABLE,
            ])->all();
            $calculation = $this->calculator->calculate($lines, (float) ($quote->discount_rate ?? 0));
            $legacyRates = $calculation->legacySpanishRateAmounts();

            // ── Create invoice ─────────────────────────────────
            $invoice = Invoice::create([
                'uuid'            => Str::uuid()->toString(),
                'reference'       => $this->numbering->nextDraftLabel(),
                'customer_id'     => $quote->customer_id,
                'date'            => now()->toDateString(),
                'status'          => Invoice::STATUS_DRAFT,
                'expiration_date' => $quote->expiration_date,
                'payment_terms'   => $quote->payment_terms,
                'sub_total'       => $calculation->subTotal,
                'discount_rate'   => $quote->discount_rate,
                'discount_amount' => $calculation->discountAmount,
                'vta'             => $calculation->totalTax,
                'vta4'            => $legacyRates['vta4'],
                'vta10'           => $legacyRates['vta10'],
                'vta21'           => $legacyRates['vta21'],
                'total'           => $calculation->grandTotal,
                'note'            => $quote->note,
            ]);

            // ── Copy cart lines (authoritative taxable base, not the
            //    quote's own possibly-inconsistent cart.total) ────────
            foreach ($quote->carts as $index => $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Invoice',
                    'cartable_id'   => $invoice->id,
                    'item_id'       => $cart->item_id,
                    'description'   => $cart->description,
                    'qty'           => $cart->qty,
                    'price'         => $cart->price,
                    'unite'         => $cart->unite ?? 'pc',
                    'discount'      => $cart->discount,
                    'total'         => $calculation->lines[$index]['taxable_base'] ?? $cart->total,
                    'vta'           => $cart->vta,
                    'tax_treatment' => $cart->tax_treatment ?? TaxTreatment::TAXABLE,
                ]);
            }

            foreach ($calculation->taxBreakdown as $row) {
                InvoiceTaxLine::create([
                    'invoice_id'   => $invoice->id,
                    'rate'         => $row['rate'],
                    'treatment'    => $row['treatment'],
                    'taxable_base' => $row['taxable_base'],
                    'tax_amount'   => $row['tax_amount'],
                ]);
            }

            // ── Audit trail on the new invoice ─────────────────
            $invoice->logHistory(InvoiceHistory::ACTION_CREATED, [
                'source'       => 'quote',
                'quote_uuid'   => $quote->uuid,
                'quote_ref'    => $quote->reference,
            ]);

            // ── Mark quote as converted ────────────────────────
            $quote->status     = Quote::STATUS_CONVERTED;
            $quote->invoice_id = $invoice->id;
            $quote->save();

            Log::info('Quote converted successfully', [
                'quote_uuid'   => $quote->uuid,
                'invoice_uuid' => $invoice->uuid,
                'invoice_id'   => $invoice->id,
            ]);

            return $invoice;
        });

        return $invoice;
    }
}
