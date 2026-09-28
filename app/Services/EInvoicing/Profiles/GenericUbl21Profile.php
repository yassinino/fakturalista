<?php

namespace App\Services\EInvoicing\Profiles;

use App\Services\EInvoicing\Contracts\EInvoiceProfileInterface;
use App\Services\EInvoicing\DTO\EInvoiceData;
use App\Services\EInvoicing\Ubl\UblInvoiceBuilder;
use App\Services\EInvoicing\Ubl\UblValidationResult;
use App\Services\EInvoicing\Ubl\UblValidator;

/**
 * The "ubl_2_1" profile (config('einvoicing.profiles')) - generic,
 * country-agnostic UBL 2.1, exactly as built/validated by Steps 2-3. This
 * class adds no logic of its own: it only exposes the existing
 * UblInvoiceBuilder/UblValidator through EInvoiceProfileInterface, so a
 * caller (App\Services\EInvoicing\EInvoiceProfileRegistry, and in turn
 * InvoiceController) never needs to know a concrete builder/validator -
 * only "a profile". A future profile (e.g. Morocco DGI, once that
 * specification exists - see docs/einvoicing-morocco-readiness.md) is
 * added the same way, alongside this one, without changing this class.
 */
class GenericUbl21Profile implements EInvoiceProfileInterface
{
    public function __construct(
        private UblInvoiceBuilder $builder = new UblInvoiceBuilder(),
        private UblValidator $validator = new UblValidator(),
    ) {
    }

    public function key(): string
    {
        return 'ubl_2_1';
    }

    public function build(EInvoiceData $data): string
    {
        return $this->builder->build($data);
    }

    public function validate(EInvoiceData $data): array
    {
        return $this->validator->validate($data);
    }

    public function validateOutput(string $document): UblValidationResult
    {
        return $this->validator->validateXsd($document);
    }
}
