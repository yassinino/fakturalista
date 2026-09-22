<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim identifier fields before validation - Morocco Phase 1B
     * (docs/morocco-phase-1b-identity.md §2). Never invented formatting,
     * just avoids accidental leading/trailing whitespace on an
     * identifier breaking a later lookup/display.
     */
    protected function prepareForValidation(): void
    {
        foreach (['ice', 'if_number', 'commercial_register', 'tax_id'] as $field) {
            if ($this->filled($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'tax_id' => 'nullable|string|max:20',
            // Orden HAC/1177/2024, Anexo, lista L7 - the only AEAT-valid
            // codes for a foreign tax/identity document (IDOtro).
            'foreign_tax_id_type' => 'nullable|string|in:02,03,04,05,06,07',
            'foreign_tax_id' => 'nullable|string|max:20',
            // Morocco Phase 1B - no format/checksum validation (not
            // verified against a primary legal source), never required.
            'ice' => 'nullable|string|max:32',
            'if_number' => 'nullable|string|max:32',
            'commercial_register' => 'nullable|string|max:64',
        ];
    }
}
