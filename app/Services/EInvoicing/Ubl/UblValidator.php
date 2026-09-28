<?php

namespace App\Services\EInvoicing\Ubl;

use App\Services\EInvoicing\DTO\EInvoiceData;
use DOMDocument;

/**
 * Two independent checks, kept separate on purpose:
 *
 *  - validate(EInvoiceData $data): array - structural completeness of the
 *    DTO *before* any XML exists (unchanged since Step 1 - still no
 *    UBL-specific or Morocco DGI rule, just "does Fakturalista have the
 *    data a UBL Invoice would need").
 *
 *  - validateXsd(string $xml): UblValidationResult - STEP 3: real UBL 2.1
 *    conformance of XML already produced by UblInvoiceBuilder, checked
 *    against the official OASIS UBL 2.1 XSD schemas bundled under
 *    resources/einvoicing/ubl/2.1/xsd/ (see config('einvoicing.xsd_schema_path')).
 *    Entirely offline - every schemaLocation in that bundle is a local
 *    relative path, nothing is ever fetched over the network.
 *
 * Still not implemented here: Schematron / business-rule validation
 * (e.g. PEPPOL BIS rules) - that is a later step, not XSD.
 */
class UblValidator
{
    /**
     * @return string[] human-readable issues; empty means $data has the
     *                   minimum a UBL Invoice would need.
     */
    public function validate(EInvoiceData $data): array
    {
        $issues = [];

        if (trim($data->invoiceNumber) === '') {
            $issues[] = 'Missing invoice number.';
        }

        if (trim($data->currency) === '') {
            $issues[] = 'Missing currency.';
        }

        if (trim($data->seller->name) === '') {
            $issues[] = 'Missing seller name.';
        }

        if (trim($data->customer->name) === '') {
            $issues[] = 'Missing customer name.';
        }

        if (empty($data->lines)) {
            $issues[] = 'Invoice has no lines.';
        }

        if ($data->totalIncludingTax < 0) {
            $issues[] = 'Total including tax is negative.';
        }

        $expectedTotal = round($data->subtotalExcludingTax + $data->totalTaxAmount, 2);
        if (abs($expectedTotal - round($data->totalIncludingTax, 2)) > 0.01) {
            $issues[] = "Totals don't add up: subtotal + tax ({$expectedTotal}) != total including tax ({$data->totalIncludingTax}).";
        }

        return $issues;
    }

    /**
     * Validates a UBL 2.1 Invoice XML string (as produced by
     * UblInvoiceBuilder::build()) against the official OASIS UBL 2.1
     * Invoice XSD. Never resolves external entities and never touches
     * the network - see the class docblock and the security note below.
     */
    public function validateXsd(string $xml): UblValidationResult
    {
        $schemaPath = config('einvoicing.xsd_schema_path');

        if (!is_string($schemaPath) || !is_file($schemaPath)) {
            return new UblValidationResult(false, [
                new UblValidationError(
                    message: "UBL 2.1 XSD schema not found at " . ($schemaPath ?: '(not configured)') . '.',
                    line: null,
                    column: null,
                    level: null,
                    code: null,
                ),
            ]);
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            // XXE hardening: never resolve external entities, never
            // substitute them inline, and LIBXML_NONET refuses any
            // network fetch a malicious DOCTYPE/entity might attempt -
            // even though our own XSDs are 100% local, a future caller
            // may feed this untrusted XML.
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;

            $wellFormed = $dom->loadXML($xml, LIBXML_NONET);

            if (!$wellFormed) {
                return new UblValidationResult(false, $this->collectErrors(
                    'XML could not be parsed (not well-formed).'
                ));
            }

            $valid = $dom->schemaValidate($schemaPath);

            return new UblValidationResult($valid, $this->collectErrors(
                $valid ? null : 'XML did not validate against the UBL 2.1 Invoice XSD.'
            ));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    /**
     * @return UblValidationError[]
     */
    private function collectErrors(?string $fallbackMessage): array
    {
        $errors = array_map(
            fn (\LibXMLError $e) => UblValidationError::fromLibXmlError($e),
            libxml_get_errors()
        );

        if (empty($errors) && $fallbackMessage !== null) {
            $errors[] = new UblValidationError($fallbackMessage, null, null, null, null);
        }

        return $errors;
    }
}
