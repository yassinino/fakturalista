<?php

namespace App\Http\Requests;

use App\Services\Tax\TaxTreatment;
use App\Rules\TaxRateTreatment;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required',
            'date' => 'required',
            'expiration_date' => 'required',
            'carts.*' => 'required',
            // Morocco Phase 1C.1 (docs/morocco-phase-1c1-generic-tax-foundation.md
            // §2/§10) - quotes go through the same authoritative
            // calculator and validation as invoices.
            'discount_rate'      => 'nullable|numeric|min:0|max:100',
            'carts.*.qty'        => 'required|numeric|min:0.001',
            'carts.*.price'      => 'required|numeric|min:0',
            'carts.*.discount'   => 'nullable|numeric|min:0|max:100',
            'carts.*.vta'        => ['nullable', 'numeric', 'min:0', 'max:100', new TaxRateTreatment],
            'carts.*.tax_treatment' => 'nullable|string|in:' . implode(',', TaxTreatment::ALL),
        ];
    }
}
