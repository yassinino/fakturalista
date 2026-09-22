<?php

namespace App\Services\Tax;

/** One system tax option. Display labels are added by TaxPresetService. */
class TaxPreset
{
    public function __construct(
        public readonly string $code,
        public readonly string $countryCode,
        public readonly float $rate,
        public readonly string $treatment,
        public readonly bool $isDefault = false,
        public readonly bool $active = true,
    ) {
    }

    public function toArray(): array
    {
        return [
            'code'         => $this->code,
            'country_code' => $this->countryCode,
            'rate'         => $this->rate,
            'treatment'    => $this->treatment,
            'is_default'   => $this->isDefault,
            'active'       => $this->active,
        ];
    }
}
