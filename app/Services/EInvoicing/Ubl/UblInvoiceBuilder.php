<?php

namespace App\Services\EInvoicing\Ubl;

use App\Services\EInvoicing\DTO\EInvoiceData;
use App\Services\EInvoicing\DTO\EInvoiceLineData;
use App\Services\EInvoicing\DTO\EInvoicePartyData;
use App\Services\EInvoicing\DTO\EInvoiceTaxData;
use App\Services\Tax\TaxTreatment;
use DOMDocument;
use DOMElement;

/**
 * Builds a UBL 2.1 Invoice XML document from an EInvoiceData DTO.
 *
 * STEP 2: generic, standards-based UBL 2.1 XML only - no Morocco DGI
 * profile, no AEAT-specific code lists, no XSD schema validation (that is
 * Step 3 - see UblValidator). Built entirely with DOMDocument, never
 * string concatenation, so every customer/company/item value is escaped
 * automatically by the DOM when it is serialised.
 *
 * Namespaces (official UBL 2.1):
 *  - (default)  urn:oasis:names:specification:ubl:schema:xsd:Invoice-2
 *  - cac:       urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2
 *  - cbc:       urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2
 *
 * Deliberately omitted rather than guessed (see the Step 2 report):
 *  - InvoiceTypeCode for a rectification (no safe generic UBL code covers
 *    every AEAT R1-R5/mode combination - see invoiceTypeCode()).
 *  - Country/IdentificationCode when only a country name is known (no
 *    reliable name-to-ISO-code lookup exists in Fakturalista today).
 *  - TaxSubtotal/TaxableAmount for a legacy vta4/vta10/vta21 breakdown row
 *    (the original taxable base was never stored - see buildTaxSubtotal()).
 */
class UblInvoiceBuilder
{
    private const INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const CAC_NS     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NS     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XMLNS_NS   = 'http://www.w3.org/2000/xmlns/';

    /**
     * Tax identifiers that describe a legal/commercial registration
     * rather than a tax scheme - placed under PartyLegalEntity/CompanyID
     * instead of a PartyTaxScheme, matching UBL's own semantics for the
     * two elements. Every other key in EInvoicePartyData::$taxIdentifiers
     * (tax_id, vat_number, ice, if_number, foreign_tax_id, ...) is a tax
     * identifier and becomes a PartyTaxScheme entry.
     */
    private const LEGAL_ENTITY_ID_KEYS = ['registration_number', 'commercial_register'];

    public function __construct(private UblUnitCodeMapper $unitMapper = new UblUnitCodeMapper())
    {
    }

    public function build(EInvoiceData $data): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $invoice = $dom->createElementNS(self::INVOICE_NS, 'Invoice');
        $invoice->setAttributeNS(self::XMLNS_NS, 'xmlns:cac', self::CAC_NS);
        $invoice->setAttributeNS(self::XMLNS_NS, 'xmlns:cbc', self::CBC_NS);
        $dom->appendChild($invoice);

        $invoice->appendChild($this->cbc($dom, 'UBLVersionID', '2.1'));
        $invoice->appendChild($this->cbc($dom, 'ID', $data->invoiceNumber));
        $invoice->appendChild($this->cbc($dom, 'IssueDate', $data->issueDate->format('Y-m-d')));

        $typeCode = $this->invoiceTypeCode($data);
        if ($typeCode !== null) {
            $invoice->appendChild($this->cbc($dom, 'InvoiceTypeCode', $typeCode));
        }

        $invoice->appendChild($this->cbc($dom, 'DocumentCurrencyCode', $data->currency));

        $supplier = $this->cac($dom, 'AccountingSupplierParty');
        $supplier->appendChild($this->buildParty($dom, $data->seller));
        $invoice->appendChild($supplier);

        $customer = $this->cac($dom, 'AccountingCustomerParty');
        $customer->appendChild($this->buildParty($dom, $data->customer));
        $invoice->appendChild($customer);

        $invoice->appendChild($this->buildTaxTotal($dom, $data));
        $invoice->appendChild($this->buildLegalMonetaryTotal($dom, $data));

        foreach (array_values($data->lines) as $index => $line) {
            /** @var EInvoiceLineData $line */
            $invoice->appendChild($this->buildInvoiceLine($dom, $line, $index + 1, $data->currency));
        }

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Failed to generate UBL XML.');
        }

        return $xml;
    }

    /**
     * UNCL1001 "380" (Commercial invoice) is the one code that safely
     * describes any regular Fakturalista invoice without distinguishing
     * F1 from F2 - a distinction EInvoiceData doesn't even carry, and
     * this step must not invent (see the Step 2 brief). A rectification
     * has no single safe generic equivalent - depending on the AEAT
     * R-type/mode (Invoice::RECTIFICATION_TYPES) it could be a full
     * credit note or a partial correction - so it is omitted rather than
     * guessed.
     */
    private function invoiceTypeCode(EInvoiceData $data): ?string
    {
        return $data->isRectification ? null : '380';
    }

    private function buildParty(DOMDocument $dom, EInvoicePartyData $party): DOMElement
    {
        $partyEl = $this->cac($dom, 'Party');

        $partyName = $this->cac($dom, 'PartyName');
        $partyName->appendChild($this->cbc($dom, 'Name', $party->name));
        $partyEl->appendChild($partyName);

        $address = $this->buildPostalAddress($dom, $party);
        if ($address !== null) {
            $partyEl->appendChild($address);
        }

        foreach ($this->taxSchemeIdentifiers($party) as $identifierValue) {
            $taxScheme = $this->cac($dom, 'PartyTaxScheme');
            $taxScheme->appendChild($this->cbc($dom, 'CompanyID', $identifierValue));
            $scheme = $this->cac($dom, 'TaxScheme');
            $scheme->appendChild($this->cbc($dom, 'ID', 'VAT'));
            $taxScheme->appendChild($scheme);
            $partyEl->appendChild($taxScheme);
        }

        $legalEntity = $this->cac($dom, 'PartyLegalEntity');
        $legalEntity->appendChild($this->cbc($dom, 'RegistrationName', $party->name));
        $legalEntityId = $this->legalEntityIdentifier($party);
        if ($legalEntityId !== null) {
            $legalEntity->appendChild($this->cbc($dom, 'CompanyID', $legalEntityId));
        }
        $partyEl->appendChild($legalEntity);

        return $partyEl;
    }

    private function buildPostalAddress(DOMDocument $dom, EInvoicePartyData $party): ?DOMElement
    {
        $hasAnyAddressData = $party->addressLine1 !== null
            || $party->addressLine2 !== null
            || $party->city !== null
            || $party->postalCode !== null
            || $party->countryCode !== null
            || $party->countryName !== null;

        if (!$hasAnyAddressData) {
            return null;
        }

        $address = $this->cac($dom, 'PostalAddress');

        if ($party->addressLine1 !== null) {
            $address->appendChild($this->cbc($dom, 'StreetName', $party->addressLine1));
        }
        if ($party->addressLine2 !== null) {
            $address->appendChild($this->cbc($dom, 'AdditionalStreetName', $party->addressLine2));
        }
        if ($party->city !== null) {
            $address->appendChild($this->cbc($dom, 'CityName', $party->city));
        }
        if ($party->postalCode !== null) {
            $address->appendChild($this->cbc($dom, 'PostalZone', $party->postalCode));
        }

        $country = $this->buildCountry($dom, $party);
        if ($country !== null) {
            $address->appendChild($country);
        }

        return $address;
    }

    /**
     * Only emits IdentificationCode when EInvoicePartyData already
     * carries an ISO country code (InvoiceMapper resolves this from
     * Customer::billingCountry(), live or - since Step 4 - from a
     * customer_snapshot taken after that fix). A snapshot taken before
     * Step 4 simply has no code to give, and Fakturalista has no
     * country-name-to-ISO-code lookup to fall back to, so a name-only
     * party gets Name without IdentificationCode rather than a guessed
     * code.
     */
    private function buildCountry(DOMDocument $dom, EInvoicePartyData $party): ?DOMElement
    {
        if ($party->countryCode === null && $party->countryName === null) {
            return null;
        }

        $country = $this->cac($dom, 'Country');

        if ($party->countryCode !== null) {
            $country->appendChild($this->cbc($dom, 'IdentificationCode', $party->countryCode));
        }
        if ($party->countryName !== null) {
            $country->appendChild($this->cbc($dom, 'Name', $party->countryName));
        }

        return $country;
    }

    /**
     * @return string[] tax-identifier values only (registration/commercial
     *                   register numbers are excluded - see legalEntityIdentifier()).
     */
    private function taxSchemeIdentifiers(EInvoicePartyData $party): array
    {
        return array_values(array_diff_key(
            $party->taxIdentifiers,
            array_flip(self::LEGAL_ENTITY_ID_KEYS)
        ));
    }

    private function legalEntityIdentifier(EInvoicePartyData $party): ?string
    {
        foreach (self::LEGAL_ENTITY_ID_KEYS as $key) {
            if (!empty($party->taxIdentifiers[$key])) {
                return $party->taxIdentifiers[$key];
            }
        }

        return null;
    }

    private function buildTaxTotal(DOMDocument $dom, EInvoiceData $data): DOMElement
    {
        $taxTotal = $this->cac($dom, 'TaxTotal');
        $taxTotal->appendChild($this->amount($dom, 'TaxAmount', $data->totalTaxAmount, $data->currency));

        foreach ($data->taxBreakdown as $row) {
            /** @var EInvoiceTaxData $row */
            $taxTotal->appendChild($this->buildTaxSubtotal($dom, $row, $data->currency));
        }

        return $taxTotal;
    }

    private function buildTaxSubtotal(DOMDocument $dom, EInvoiceTaxData $row, string $currency): DOMElement
    {
        $subtotal = $this->cac($dom, 'TaxSubtotal');

        // InvoiceMapper::mapTaxBreakdown() hardcodes taxableBase to 0.0
        // for a legacy vta4/vta10/vta21 row because the original base was
        // never stored. A real 0 base can never pair with a non-zero tax
        // amount, so that exact combination is the signal that this is
        // the known legacy gap, not a genuine zero - omit TaxableAmount
        // rather than invent a value for it.
        $isUnreliableLegacyBase = $row->taxableBase === 0.0 && $row->taxAmount !== 0.0;
        if (!$isUnreliableLegacyBase) {
            $subtotal->appendChild($this->amount($dom, 'TaxableAmount', $row->taxableBase, $currency));
        }

        $subtotal->appendChild($this->amount($dom, 'TaxAmount', $row->taxAmount, $currency));

        $category = $this->cac($dom, 'TaxCategory');
        $category->appendChild($this->cbc($dom, 'ID', $this->taxCategoryCode($row->treatment, $row->rate)));
        $category->appendChild($this->cbc($dom, 'Percent', $this->decimal($row->rate)));
        $scheme = $this->cac($dom, 'TaxScheme');
        $scheme->appendChild($this->cbc($dom, 'ID', 'VAT'));
        $category->appendChild($scheme);
        $subtotal->appendChild($category);

        return $subtotal;
    }

    /**
     * UNCL5305 categories - a generic, universally standard code list
     * (not Morocco/AEAT-specific): S = standard rate, Z = zero-rated
     * (taxable but 0%), E = exempt, O = outside the scope of VAT. Derived
     * directly from Fakturalista's own TaxTreatment, never invented.
     */
    private function taxCategoryCode(string $treatment, float $rate): string
    {
        return match ($treatment) {
            TaxTreatment::EXEMPT => 'E',
            TaxTreatment::OUT_OF_SCOPE => 'O',
            default => $rate > 0.0 ? 'S' : 'Z',
        };
    }

    private function buildLegalMonetaryTotal(DOMDocument $dom, EInvoiceData $data): DOMElement
    {
        $total = $this->cac($dom, 'LegalMonetaryTotal');

        // Reused directly from EInvoiceData's already-computed totals
        // (Fakturalista's own sub_total/vta/total columns via
        // InvoiceMapper) rather than re-summed from lines, so the XML
        // can never drift from the invoice's own calculated totals.
        $total->appendChild($this->amount($dom, 'LineExtensionAmount', $data->subtotalExcludingTax, $data->currency));
        $total->appendChild($this->amount($dom, 'TaxExclusiveAmount', $data->subtotalExcludingTax, $data->currency));
        $total->appendChild($this->amount($dom, 'TaxInclusiveAmount', $data->totalIncludingTax, $data->currency));

        $allowanceTotal = round(array_sum(array_map(
            fn (EInvoiceLineData $line) => $line->discountAmount,
            $data->lines
        )), 2);
        if ($allowanceTotal > 0.0) {
            $total->appendChild($this->amount($dom, 'AllowanceTotalAmount', $allowanceTotal, $data->currency));
        }

        $total->appendChild($this->amount($dom, 'PayableAmount', $data->amountPayable, $data->currency));

        return $total;
    }

    private function buildInvoiceLine(DOMDocument $dom, EInvoiceLineData $line, int $lineNumber, string $currency): DOMElement
    {
        $lineEl = $this->cac($dom, 'InvoiceLine');
        $lineEl->appendChild($this->cbc($dom, 'ID', (string) $lineNumber));

        $quantity = $this->cbc($dom, 'InvoicedQuantity', $this->decimal($line->quantity));
        $quantity->setAttribute('unitCode', $this->unitMapper->map($line->unitOfMeasure));
        $lineEl->appendChild($quantity);

        $lineEl->appendChild($this->amount($dom, 'LineExtensionAmount', $line->taxableBase, $currency));

        $item = $this->cac($dom, 'Item');
        $item->appendChild($this->cbc($dom, 'Description', $line->description));

        $taxCategory = $this->cac($dom, 'ClassifiedTaxCategory');
        $taxCategory->appendChild($this->cbc($dom, 'ID', $this->taxCategoryCode($line->taxTreatment, $line->taxRate)));
        $taxCategory->appendChild($this->cbc($dom, 'Percent', $this->decimal($line->taxRate)));
        $scheme = $this->cac($dom, 'TaxScheme');
        $scheme->appendChild($this->cbc($dom, 'ID', 'VAT'));
        $taxCategory->appendChild($scheme);
        $item->appendChild($taxCategory);

        $lineEl->appendChild($item);

        $price = $this->cac($dom, 'Price');
        $price->appendChild($this->amount($dom, 'PriceAmount', $line->unitPrice, $currency));
        $lineEl->appendChild($price);

        return $lineEl;
    }

    // ── XML construction helpers ──────────────────────────────────────
    // DOMDocument text nodes escape &, <, >, etc. automatically on
    // serialisation - no manual escaping and no string concatenation
    // anywhere in this class.

    private function cac(DOMDocument $dom, string $name): DOMElement
    {
        return $dom->createElementNS(self::CAC_NS, 'cac:' . $name);
    }

    private function cbc(DOMDocument $dom, string $name, string $value): DOMElement
    {
        $el = $dom->createElementNS(self::CBC_NS, 'cbc:' . $name);
        $el->appendChild($dom->createTextNode($value));

        return $el;
    }

    private function amount(DOMDocument $dom, string $name, float $value, string $currency): DOMElement
    {
        $el = $this->cbc($dom, $name, $this->decimal($value));
        $el->setAttribute('currencyID', $currency);

        return $el;
    }

    /**
     * Deterministic decimal formatting for every monetary value, quantity
     * and percentage in the document: always a fixed 2 decimal places,
     * '.' separator, never scientific notation or a locale-dependent
     * comma.
     */
    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
