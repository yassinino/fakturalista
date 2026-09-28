<?php

namespace App\Services\EInvoicing\Ubl;

/**
 * Structured result of UblValidator::validateXsd() - deliberately not a
 * bare bool, so a caller always has the actual libxml errors (message,
 * line, column, level/code - see UblValidationError) when $valid is false.
 */
final class UblValidationResult
{
    /**
     * @param UblValidationError[] $errors
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
    ) {
    }

    public function toArray(): array
    {
        return [
            'valid'  => $this->valid,
            'errors' => array_map(fn (UblValidationError $e) => $e->toArray(), $this->errors),
        ];
    }
}
