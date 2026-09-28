<?php

namespace App\Services\EInvoicing\Ubl;

/**
 * Maps a Fakturalista Cart/Item unit label (Cart::$unite - never read from
 * or written to here, only accepted as a plain string) to a UN/CEFACT
 * Recommendation 20 unit code, for UBL's cbc:InvoicedQuantity/@unitCode.
 *
 * Deliberately small, per the Step 2 brief: this is not a general unit
 * database. It covers exactly the values Fakturalista's own UI offers
 * today (resources/js/views/admin/items/{create,edit}.vue: "pc", "kg")
 * plus the plain FR/ES/EN words already seen in free-text `unite` data
 * (e.g. "hour", "piece" - see tests/Feature/EInvoiceMapperTest.php).
 * Anything else falls back to a single configurable default rather than
 * guessing a specific code.
 */
class UblUnitCodeMapper
{
    /**
     * UN/CEFACT "C62" (piece/unit) - the safest generic fallback when a
     * unit label isn't recognised. Overridable via
     * config('einvoicing.default_unit_code').
     */
    private const DEFAULT_FALLBACK_CODE = 'C62';

    /** @var array<string, string> lower-cased label => UN/CEFACT Rec 20 code */
    private const LABEL_TO_CODE = [
        // piece / unit
        'pc'      => 'C62',
        'pcs'     => 'C62',
        'piece'   => 'C62',
        'pieces'  => 'C62',
        'pièce'   => 'C62',
        'pièces'  => 'C62',
        'unit'    => 'C62',
        'units'   => 'C62',
        'unite'   => 'C62',
        'unites'  => 'C62',
        'unité'   => 'C62',
        'unités'  => 'C62',
        'u'       => 'C62',
        'pieza'   => 'C62',
        'piezas'  => 'C62',
        'unidad'  => 'C62',
        'unidades' => 'C62',

        // kilogram
        'kg'          => 'KGM',
        'kilogram'    => 'KGM',
        'kilograms'   => 'KGM',
        'kilogramme'  => 'KGM',
        'kilogrammes' => 'KGM',
        'kilogramo'   => 'KGM',
        'kilogramos'  => 'KGM',

        // hour
        'hour'  => 'HUR',
        'hours' => 'HUR',
        'h'     => 'HUR',
        'heure' => 'HUR',
        'heures' => 'HUR',
        'hora'  => 'HUR',
        'horas' => 'HUR',

        // day
        'day'   => 'DAY',
        'days'  => 'DAY',
        'j'     => 'DAY',
        'jour'  => 'DAY',
        'jours' => 'DAY',
        'dia'   => 'DAY',
        'día'   => 'DAY',
        'dias'  => 'DAY',
        'días'  => 'DAY',

        // month
        'month'  => 'MON',
        'months' => 'MON',
        'mois'   => 'MON',
        'mes'    => 'MON',
        'meses'  => 'MON',
    ];

    /**
     * @param string|null $label the raw Cart::$unite / Item::$unite value
     */
    public function map(?string $label): string
    {
        if ($label === null) {
            return $this->fallback();
        }

        $key = mb_strtolower(trim($label));
        if ($key === '') {
            return $this->fallback();
        }

        return self::LABEL_TO_CODE[$key] ?? $this->fallback();
    }

    private function fallback(): string
    {
        return (string) config('einvoicing.default_unit_code', self::DEFAULT_FALLBACK_CODE);
    }
}
