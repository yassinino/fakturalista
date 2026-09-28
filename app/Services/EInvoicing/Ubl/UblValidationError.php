<?php

namespace App\Services\EInvoicing\Ubl;

/**
 * One libxml error/warning produced while parsing or XSD-validating a UBL
 * document (UblValidator::validateXsd()) - a structured mirror of
 * PHP's \LibXMLError, so callers never need to touch libxml_get_errors()
 * themselves.
 */
final class UblValidationError
{
    public function __construct(
        public readonly string $message,
        public readonly ?int $line,
        public readonly ?int $column,
        // \LibXMLError::$level: LIBXML_ERR_WARNING (1), LIBXML_ERR_ERROR (2)
        // or LIBXML_ERR_FATAL (3). Null when this error didn't come from
        // libxml (e.g. "schema file not found").
        public readonly ?int $level,
        // \LibXMLError::$code - the libxml error code (e.g. 1824 for
        // "cvc-complex-type.2.4.a", 1871 for a missing required element).
        public readonly ?int $code,
    ) {
    }

    public static function fromLibXmlError(\LibXMLError $error): self
    {
        return new self(
            message: trim($error->message),
            line: $error->line > 0 ? $error->line : null,
            column: $error->column > 0 ? $error->column : null,
            level: $error->level,
            code: $error->code > 0 ? $error->code : null,
        );
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'line'    => $this->line,
            'column'  => $this->column,
            'level'   => $this->level,
            'code'    => $this->code,
        ];
    }
}
