<?php

namespace App\Services\Verifactu;

use App\Exceptions\Verifactu\VerifactuXmlValidationException;

/**
 * Validates a VERI*FACTU RegistroAlta/RegistroAnulacion XML document
 * against the official, vendored AEAT schema (see
 * docs/verifactu-xml-spec-freeze.md and resources/verifactu/xsd/README.md
 * for exactly which version and why it's vendored rather than fetched
 * live). Well-formed XML is not enough - this is the layer that actually
 * enforces the schema.
 */
class VerifactuXmlValidator
{
    private const XSD_PATH = 'resources/verifactu/xsd/2026-09-20/SuministroInformacion.local.xsd';

    /**
     * @throws VerifactuXmlValidationException if the document is not
     *         well-formed XML, or does not conform to the schema.
     */
    public function validate(string $xml): void
    {
        $errors = $this->collectErrors($xml);

        if (!empty($errors)) {
            throw new VerifactuXmlValidationException($errors);
        }
    }

    public function isValid(string $xml): bool
    {
        return empty($this->collectErrors($xml));
    }

    /**
     * @return string[] Empty when valid.
     */
    private function collectErrors(string $xml): array
    {
        $previousSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            $loaded   = $document->loadXML($xml);

            if (!$loaded) {
                return $this->formatLibXmlErrors();
            }

            $valid = $document->schemaValidate(base_path(self::XSD_PATH));

            return $valid ? [] : $this->formatLibXmlErrors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }
    }

    /**
     * @return string[]
     */
    private function formatLibXmlErrors(): array
    {
        $messages = [];

        foreach (libxml_get_errors() as $error) {
            $messages[] = sprintf('Línea %d: %s', $error->line, trim($error->message));
        }

        return $messages ?: ['El documento XML no es válido, pero no se pudo determinar el motivo exacto.'];
    }
}
