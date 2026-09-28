<?php

namespace App\Services\EInvoicing;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\EInvoicing\DTO\EInvoiceData;
use App\Services\EInvoicing\DTO\EInvoiceLineData;
use App\Services\EInvoicing\DTO\EInvoicePartyData;
use App\Services\EInvoicing\DTO\EInvoiceTaxData;
use App\Services\Tax\TaxTreatment;
use App\Services\TenantContextService;
use Illuminate\Support\Carbon;

/**
 * Converts an existing Fakturalista Invoice into the generic, format-
 * agnostic EInvoiceData DTO - Step 1 of the e-invoicing groundwork. Pure
 * read/mapping: never writes to the invoice, never changes numbering,
 * calculation, or PDF behavior. Reuses the exact same data sources
 * App\Services\Pdf\TemplateRendererService already reads for rendering
 * an invoice, so this DTO can never show identity data the PDF doesn't
 * already show.
 *
 * Snapshot-first, like the PDF: an issued invoice's company_snapshot/
 * customer_snapshot (Morocco Phase 1B, immutable identity at issuance
 * time) is preferred over the live CompanyProfile/Customer row whenever
 * it exists; only a draft or a pre-snapshot legacy invoice falls back to
 * live data. This matters for e-invoicing specifically: the document
 * must describe the seller/customer as they were when the invoice was
 * issued, not as they are today.
 *
 * Customer country code (Step 4): Customer::identitySnapshot() now stores
 * both the billing country's name AND its ISO code (Country::$code, the
 * same relation the seller snapshot already relied on), so an invoice
 * issued from Step 4 onward carries a snapshotted countryCode. Historical
 * snapshots taken before that change simply lack the key - their
 * countryCode is null here, never guessed from the country name, and
 * they are never rewritten. See mapCustomer().
 */
class InvoiceMapper
{
    public function __construct(private TenantContextService $context)
    {
    }

    public function map(Invoice $invoice): EInvoiceData
    {
        $invoice->loadMissing(['customer', 'carts', 'taxLines']);

        $currency = $this->context->currency();

        return new EInvoiceData(
            invoiceNumber: $this->invoiceNumber($invoice),
            issueDate: Carbon::parse($invoice->date),
            dueDate: $invoice->expiration_date ? Carbon::parse($invoice->expiration_date) : null,
            currency: $currency,
            seller: $this->mapSeller($invoice),
            customer: $this->mapCustomer($invoice),
            lines: $this->mapLines($invoice),
            taxBreakdown: $this->mapTaxBreakdown($invoice),
            subtotalExcludingTax: (float) ($invoice->sub_total ?? 0),
            totalTaxAmount: (float) ($invoice->vta ?? 0),
            totalIncludingTax: (float) ($invoice->total ?? 0),
            // Always the invoice's own stated total, regardless of
            // Fakturalista's internal paid/issued status - a UBL
            // Invoice's PayableAmount describes the document's own
            // terms, not a live "amount still owed" balance query.
            // Fakturalista has no partial-payment tracking (status is
            // binary paid/unpaid; see Invoice::STATUS_*), so there is no
            // "already collected" figure to subtract even if a future
            // profile wanted to.
            amountPayable: (float) ($invoice->total ?? 0),
            note: $invoice->note,
            isRectification: $invoice->isRectification(),
        );
    }

    /**
     * invoice_series+invoice_number (InvoiceNumberingService, assigned at
     * issuance - App\Models\Invoice's own legal numbering columns) when
     * present, else the draft `reference` - never invented here.
     */
    private function invoiceNumber(Invoice $invoice): string
    {
        if ($invoice->hasLegalNumber()) {
            return $invoice->invoice_series . '-' . $invoice->invoice_number;
        }

        return (string) ($invoice->reference ?? '');
    }

    private function mapSeller(Invoice $invoice): EInvoicePartyData
    {
        $snapshot = $invoice->company_snapshot;
        $identity = $snapshot ?? CompanyProfile::first()?->identitySnapshot() ?? [];

        $taxIdentifiers = array_filter([
            'tax_id'              => $identity['tax_id'] ?? null,
            'vat_number'          => $identity['vat_number'] ?? null,
            'registration_number' => $identity['registration_number'] ?? null,
            'ice'                 => $identity['ice'] ?? null,
            'if_number'           => $identity['if_number'] ?? null,
        ], fn ($v) => !empty($v));

        return new EInvoicePartyData(
            name: $identity['trade_name'] ?? $identity['legal_name'] ?? config('app.name'),
            tradeName: $identity['trade_name'] ?? null,
            addressLine1: $identity['address_line1'] ?? null,
            addressLine2: $identity['address_line2'] ?? null,
            city: $identity['city'] ?? null,
            postalCode: $identity['postal_code'] ?? null,
            // Present on both the snapshot and the live CompanyProfile row.
            countryCode: $identity['country_code'] ?? null,
            countryName: $identity['country'] ?? null,
            taxIdentifiers: $taxIdentifiers,
            email: $identity['email'] ?? null,
            phone: $identity['phone'] ?? null,
        );
    }

    private function mapCustomer(Invoice $invoice): EInvoicePartyData
    {
        $snapshot = $invoice->customer_snapshot;
        $identity = $snapshot ?? $invoice->customer?->identitySnapshot() ?? [];

        $taxIdentifiers = array_filter([
            'tax_id'              => $identity['tax_id'] ?? null,
            'vat_number'          => $identity['vat_number'] ?? null,
            'commercial_register' => $identity['commercial_register'] ?? null,
            'ice'                 => $identity['ice'] ?? null,
            'if_number'           => $identity['if_number'] ?? null,
            'foreign_tax_id'      => $identity['foreign_tax_id'] ?? null,
        ], fn ($v) => !empty($v));

        // Customer::identitySnapshot() now carries 'country_code' (Step 4)
        // alongside the existing 'country' name, resolved from the same
        // Country/billingCountry() relation. A snapshot taken before that
        // change simply has no such key - $identity['country_code'] is
        // then null, and no code is guessed for it. See the class
        // docblock: this never rewrites an existing snapshot.
        $countryCode = $identity['country_code'] ?? null;

        return new EInvoicePartyData(
            name: $identity['name'] ?? $identity['company_name'] ?? '',
            tradeName: $identity['company_name'] ?? null,
            addressLine1: $identity['address_billing'] ?? null,
            addressLine2: null,
            city: $identity['city_billing'] ?? null,
            postalCode: $identity['post_code_billing'] ?? null,
            countryCode: $countryCode,
            countryName: $identity['country'] ?? null,
            taxIdentifiers: $taxIdentifiers,
            email: $identity['email'] ?? null,
            phone: $identity['phone'] ?? null,
        );
    }

    /**
     * @return EInvoiceLineData[]
     */
    private function mapLines(Invoice $invoice): array
    {
        return $invoice->carts->map(function ($cart) {
            $qty       = (float) ($cart->qty ?? 0);
            $price     = (float) ($cart->price ?? 0);
            $discount  = (float) ($cart->discount ?? 0);
            $rate      = (float) ($cart->vta ?? 0);
            $treatment = $cart->tax_treatment ?? TaxTreatment::TAXABLE;

            $gross          = round($qty * $price, 2);
            $discountAmount = round($gross * $discount / 100, 2);
            $taxableBase    = round($gross - $discountAmount, 2);
            // Derived, not persisted per-line - see EInvoiceLineData's docblock.
            $taxAmount      = $treatment === TaxTreatment::TAXABLE
                ? round($taxableBase * $rate / 100, 2)
                : 0.0;

            return new EInvoiceLineData(
                description: (string) ($cart->description ?? ''),
                quantity: $qty,
                unitOfMeasure: $cart->unite,
                unitPrice: $price,
                grossAmount: $gross,
                discountPercent: $discount,
                discountAmount: $discountAmount,
                taxableBase: $taxableBase,
                taxRate: $rate,
                taxTreatment: TaxTreatment::isValid($treatment) ? $treatment : TaxTreatment::TAXABLE,
                taxAmount: $taxAmount,
                lineTotal: $taxableBase,
            );
        })->all();
    }

    /**
     * Prefers the generic invoice_tax_lines breakdown (Morocco Phase
     * 1C.1 - App\Models\InvoiceTaxLine, written for every invoice issued
     * since that phase). Falls back to the legacy vta4/vta10/vta21
     * columns only for invoices issued before invoice_tax_lines existed
     * - same fallback TemplateRendererService already uses for the PDF.
     *
     * @return EInvoiceTaxData[]
     */
    private function mapTaxBreakdown(Invoice $invoice): array
    {
        if ($invoice->taxLines->isNotEmpty()) {
            return $invoice->taxLines->map(fn ($line) => new EInvoiceTaxData(
                rate: (float) $line->rate,
                treatment: $line->treatment ?? TaxTreatment::TAXABLE,
                taxableBase: (float) $line->taxable_base,
                taxAmount: (float) $line->tax_amount,
            ))->all();
        }

        $legacy = [];
        foreach ([4, 10, 21] as $rate) {
            $amount = (float) ($invoice->{'vta' . $rate} ?? 0);
            if ($amount != 0) {
                $legacy[] = new EInvoiceTaxData(
                    rate: (float) $rate,
                    treatment: TaxTreatment::TAXABLE,
                    // Legacy columns only ever stored the tax amount, not
                    // the taxable base it was computed from - genuinely
                    // unavailable here, not just unmapped.
                    taxableBase: 0.0,
                    taxAmount: $amount,
                );
            }
        }

        return $legacy;
    }
}
