<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe invoice numbering.
 *
 * Replaces the previous `Invoice::lockForUpdate()->orderBy('id','desc')->first()->id + 1`
 * approach (duplicated in InvoiceController and QuoteToInvoiceService), which
 * could reissue an already-used reference if the invoice holding the current
 * highest id was soft-deleted.
 *
 * Three independent series are maintained (see invoice_number_sequences):
 *  - "invoice-draft"          internal, provisional label shown before issuance.
 *  - "invoice-default"        the definitive legal number, assigned at issue().
 *  - "invoice-rectification"  a separate series for rectificative invoices,
 *                             required by RD 1619/2012 art. 6.5 ("será
 *                             obligatoria, en todo caso, la expedición en
 *                             series específicas de ... las rectificativas").
 *
 * Each series is independently correlative and gap-free from the point it
 * starts. Numbers are never reused, even if the invoice they were assigned
 * to is later soft-deleted (drafts) - deletion of a non-draft invoice is
 * blocked entirely elsewhere (InvoiceController::destroy()).
 */
class InvoiceNumberingService
{
    public const SERIES_DRAFT          = 'invoice-draft';
    public const SERIES_DEFAULT        = 'invoice-default';
    public const SERIES_RECTIFICATION  = 'invoice-rectification';

    /**
     * Assign a provisional, non-legal draft label. Safe to call any number
     * of times for any number of drafts - never collides, never reused.
     */
    public function nextDraftLabel(): string
    {
        return 'INV-' . $this->reserve(self::SERIES_DRAFT);
    }

    /**
     * Assign the definitive legal invoice number and overwrite `reference`
     * with its formatted form. Must only be called once, at the point an
     * invoice is actually issued - never at draft-creation time.
     */
    public function assignLegalNumber(Invoice $invoice, CompanyProfile $company): Invoice
    {
        return DB::transaction(function () use ($invoice, $company) {
            $number = $this->reserve(self::SERIES_DEFAULT);

            $invoice->invoice_series = self::SERIES_DEFAULT;
            $invoice->invoice_number = $number;
            $invoice->reference      = $this->format(
                $company->invoice_number_format ?: '{PREFIX}-{YYYY}-{NUMBER}',
                $company->invoice_prefix ?: 'INV',
                $invoice->date,
                $number
            );

            if (empty($invoice->invoice_type)) {
                $invoice->invoice_type = Invoice::TYPE_F1;
            }

            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Assign the definitive number for a rectificative invoice, from its
     * own separate series.
     */
    public function assignRectificationNumber(Invoice $invoice, CompanyProfile $company): Invoice
    {
        return DB::transaction(function () use ($invoice, $company) {
            $number = $this->reserve(self::SERIES_RECTIFICATION);

            $invoice->invoice_series = self::SERIES_RECTIFICATION;
            $invoice->invoice_number = $number;
            $invoice->reference      = $this->format(
                $company->invoice_number_format ?: '{PREFIX}-{YYYY}-{NUMBER}',
                $company->rectification_prefix ?: 'R',
                $invoice->date,
                $number
            );

            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Reserve the next number in a series. Locks the sequence row for the
     * duration of the surrounding transaction so two concurrent callers can
     * never receive the same number.
     */
    private function reserve(string $seriesKey): int
    {
        return DB::transaction(function () use ($seriesKey) {
            $sequence = InvoiceNumberSequence::where('series_key', $seriesKey)->lockForUpdate()->first();

            if (!$sequence) {
                // Guard against two concurrent requests both finding no row
                // and both trying to create one - the series_key unique
                // constraint makes the loser's insert fail; it then simply
                // re-selects (and locks) the winner's row.
                try {
                    InvoiceNumberSequence::create(['series_key' => $seriesKey, 'next_number' => 1]);
                } catch (QueryException $e) {
                    // Another request created it first - fall through to re-select.
                }

                $sequence = InvoiceNumberSequence::where('series_key', $seriesKey)->lockForUpdate()->first();
            }

            $number = $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->save();

            return $number;
        });
    }

    private function format(string $template, string $prefix, ?string $date, int $number): string
    {
        $year = $date ? Carbon::parse($date)->format('Y') : now()->format('Y');

        return strtr($template, [
            '{PREFIX}' => $prefix,
            '{YYYY}'   => $year,
            '{NUMBER}' => str_pad((string) $number, 4, '0', STR_PAD_LEFT),
        ]);
    }
}
