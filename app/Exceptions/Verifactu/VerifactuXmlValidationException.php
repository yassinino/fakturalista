<?php

namespace App\Exceptions\Verifactu;

/**
 * A generated (or supplied) VERI*FACTU XML document failed schema
 * validation. Carries the clean, structured libxml error messages -
 * never the raw LibXMLError objects/stack traces - so callers can log or
 * display something useful without leaking libxml internals.
 */
class VerifactuXmlValidationException extends \RuntimeException
{
    /** @var string[] */
    private array $validationErrors;

    /**
     * @param string[] $validationErrors
     */
    public function __construct(array $validationErrors)
    {
        $this->validationErrors = $validationErrors;

        parent::__construct(
            'El XML generado no es válido según el esquema oficial de la AEAT: ' . implode(' | ', $validationErrors)
        );
    }

    /**
     * @return string[]
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
