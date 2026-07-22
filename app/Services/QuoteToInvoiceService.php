<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuoteToInvoiceService
{
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

            $discountRate = (float) ($quote->discount_rate ?? 0);

            // Per-VAT-rate breakdowns derived from quote cart lines
            $calcVta = function (int $rate) use ($quote, $discountRate): float {
                $raw = $quote->carts
                    ->filter(fn ($c) => (int) $c->vta === $rate)
                    ->sum(fn ($c) => ($c->total * $rate) / 100);

                return round($raw - ($raw * $discountRate / 100), 2);
            };

            // ── Create invoice ─────────────────────────────────
            $invoice = Invoice::create([
                'uuid'            => Str::uuid()->toString(),
                'reference'       => $this->nextInvoiceReference(),
                'customer_id'     => $quote->customer_id,
                'date'            => now()->toDateString(),
                'status'          => Invoice::STATUS_DRAFT,
                'expiration_date' => $quote->expiration_date,
                'payment_terms'   => $quote->payment_terms,
                'sub_total'       => $quote->sub_total,
                'discount_rate'   => $quote->discount_rate,
                'discount_amount' => $quote->discount_amount,
                'vta'             => $quote->vta,
                'vta4'            => $calcVta(4),
                'vta10'           => $calcVta(10),
                'vta21'           => $calcVta(21),
                'total'           => $quote->total,
                'note'            => $quote->note,
            ]);

            // ── Copy cart lines ────────────────────────────────
            foreach ($quote->carts as $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Invoice',
                    'cartable_id'   => $invoice->id,
                    'item_id'       => $cart->item_id,
                    'description'   => $cart->description,
                    'qty'           => $cart->qty,
                    'price'         => $cart->price,
                    'unite'         => $cart->unite ?? 'pc',
                    'discount'      => $cart->discount,
                    'total'         => $cart->total,
                    'vta'           => $cart->vta,
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

    /**
     * Generate the next invoice reference using the same logic as InvoiceController.
     * Must be called inside a DB transaction so the FOR UPDATE lock is held
     * until the new invoice row is written.
     */
    private function nextInvoiceReference(): string
    {
        $last = Invoice::lockForUpdate()->orderBy('id', 'desc')->first();
        $n    = isset($last) ? $last->id + 1 : 1;

        return 'INV-' . $n;
    }
}
