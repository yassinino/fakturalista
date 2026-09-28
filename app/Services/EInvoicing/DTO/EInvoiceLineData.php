<?php

namespace App\Services\EInvoicing\DTO;

/**
 * One invoice line - Step 1 of the e-invoicing groundwork. Field names
 * and math mirror App\Services\Tax\DocumentCalculationService's own
 * per-line result shape (gross_amount/discount_amount/taxable_base) so
 * a Step 2 mapping to UBL's InvoiceLine stays a direct field-for-field
 * translation, not a re-derivation.
 */
final class EInvoiceLineData
{
    public function __construct(
        public readonly string $description,
        public readonly float $quantity,
        // Free-text unit label as stored on the cart line (e.g. "piece",
        // "hour") - NOT a UN/CEFACT unit code (UBL's cbc:UnitCode, e.g.
        // "C62"/"HUR"). See the mapper's class docblock: that code list
        // doesn't exist in Fakturalista yet and is a Step 2 concern.
        public readonly ?string $unitOfMeasure,
        public readonly float $unitPrice,
        public readonly float $grossAmount,
        public readonly float $discountPercent,
        public readonly float $discountAmount,
        // Net amount after discount, before tax - UBL's LineExtensionAmount.
        public readonly float $taxableBase,
        public readonly float $taxRate,
        // App\Services\Tax\TaxTreatment::TAXABLE|EXEMPT|OUT_OF_SCOPE
        public readonly string $taxTreatment,
        // Derived (taxableBase * taxRate / 100 for taxable lines, 0
        // otherwise) - Fakturalista doesn't persist a per-line tax amount
        // today, only the document-level breakdown (invoice_tax_lines).
        // See the mapper's docblock.
        public readonly float $taxAmount,
        public readonly float $lineTotal,
    ) {
    }

    public function toArray(): array
    {
        return [
            'description'      => $this->description,
            'quantity'         => $this->quantity,
            'unit_of_measure'  => $this->unitOfMeasure,
            'unit_price'       => $this->unitPrice,
            'gross_amount'     => $this->grossAmount,
            'discount_percent' => $this->discountPercent,
            'discount_amount'  => $this->discountAmount,
            'taxable_base'     => $this->taxableBase,
            'tax_rate'         => $this->taxRate,
            'tax_treatment'    => $this->taxTreatment,
            'tax_amount'       => $this->taxAmount,
            'line_total'       => $this->lineTotal,
        ];
    }
}
