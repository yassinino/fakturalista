<?php

namespace App\Services\EInvoicing\Contracts;

use App\Services\EInvoicing\DTO\EInvoiceData;
use App\Services\EInvoicing\Ubl\UblValidationResult;

/**
 * A single e-invoicing output format ("profile") - generic UBL 2.1 today
 * (App\Services\EInvoicing\Profiles\GenericUbl21Profile), and potentially
 * a Morocco DGI profile later, once that specification is actually
 * available (see docs/einvoicing-morocco-readiness.md - nothing DGI-
 * specific exists yet). Every profile consumes the same generic
 * EInvoiceData - a profile never reaches back into Fakturalista's own
 * Invoice/Customer/CompanyProfile models directly.
 *
 * Registered in config('einvoicing.profiles') - resolved by
 * App\Services\EInvoicing\EInvoiceProfileRegistry, the one seam a caller
 * (e.g. InvoiceController) goes through instead of coupling to a
 * specific profile/builder/validator.
 */
interface EInvoiceProfileInterface
{
    /**
     * The config key this profile is registered under (e.g. "ubl_2_1").
     */
    public function key(): string;

    /**
     * Build the profile's output document (XML for UBL) for the given
     * invoice data.
     */
    public function build(EInvoiceData $data): string;

    /**
     * Check $data has everything this profile requires, without
     * building the document. Returns a list of human-readable issues;
     * an empty array means $data is structurally usable.
     */
    public function validate(EInvoiceData $data): array;

    /**
     * Validate a document this profile has already built (build()'s
     * output) against this profile's own format-conformance rules - the
     * official OASIS UBL 2.1 XSD for GenericUbl21Profile today. Distinct
     * from validate() above, which checks EInvoiceData *before* any
     * document exists.
     *
     * UblValidationResult's shape (valid + structured errors) is reused
     * here rather than invented again - see App\Services\EInvoicing\Ubl\UblValidator.
     */
    public function validateOutput(string $document): UblValidationResult;
}
