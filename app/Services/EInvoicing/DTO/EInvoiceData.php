<?php

namespace App\Services\EInvoicing\DTO;

use Carbon\Carbon;

/**
 * Generic, format-agnostic representation of one invoice, ready to be
 * handed to any e-invoicing profile (UBL 2.1 today; a future Morocco DGI
 * profile later would consume the exact same DTO). Nothing in this class
 * is specific to UBL or to any country's tax authority - see
 * App\Services\EInvoicing\InvoiceMapper for where this is built from an
 * actual Fakturalista Invoice.
 */
final class EInvoiceData
{
    /**
     * @param EInvoiceLineData[] $lines
     * @param EInvoiceTaxData[]  $taxBreakdown
     */
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly Carbon $issueDate,
        public readonly ?Carbon $dueDate,
        // ISO 4217 (e.g. "MAD", "EUR") - the tenant's own configured
        // currency (App\Services\TenantContextService::currency()).
        // Fakturalista invoices don't store a per-invoice currency today.
        public readonly string $currency,
        public readonly EInvoicePartyData $seller,
        public readonly EInvoicePartyData $customer,
        public readonly array $lines,
        public readonly array $taxBreakdown,
        public readonly float $subtotalExcludingTax,
        public readonly float $totalTaxAmount,
        public readonly float $totalIncludingTax,
        // Equal to totalIncludingTax - see the mapper's docblock for why
        // this is never reduced by amounts already collected.
        public readonly float $amountPayable,
        public readonly ?string $note,
        // True for an R1-R5 (AEAT) rectificative invoice. A real UBL
        // InvoiceTypeCode/CreditNote decision is a Step 2 concern - see
        // the mapper's docblock.
        public readonly bool $isRectification,
    ) {
    }

    public function toArray(): array
    {
        return [
            'invoice_number'          => $this->invoiceNumber,
            'issue_date'              => $this->issueDate->toDateString(),
            'due_date'                => $this->dueDate?->toDateString(),
            'currency'                => $this->currency,
            'seller'                  => $this->seller->toArray(),
            'customer'                => $this->customer->toArray(),
            'lines'                   => array_map(fn (EInvoiceLineData $l) => $l->toArray(), $this->lines),
            'tax_breakdown'           => array_map(fn (EInvoiceTaxData $t) => $t->toArray(), $this->taxBreakdown),
            'subtotal_excluding_tax'  => $this->subtotalExcludingTax,
            'total_tax_amount'        => $this->totalTaxAmount,
            'total_including_tax'     => $this->totalIncludingTax,
            'amount_payable'          => $this->amountPayable,
            'note'                    => $this->note,
            'is_rectification'        => $this->isRectification,
        ];
    }
}
