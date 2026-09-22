<?php

namespace App\Rules;

use App\Services\Tax\TaxTreatment;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/** A non-taxable treatment cannot carry a positive tax percentage. */
class TaxRateTreatment implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $treatmentPath = preg_replace('/vta$/', 'tax_treatment', $attribute);
        $treatment = data_get($this->data, $treatmentPath, TaxTreatment::TAXABLE);

        if (in_array($treatment, [TaxTreatment::EXEMPT, TaxTreatment::OUT_OF_SCOPE], true) && (float) $value != 0) {
            $fail('Exempt and out-of-scope lines must have a zero tax rate.');
        }
    }
}
