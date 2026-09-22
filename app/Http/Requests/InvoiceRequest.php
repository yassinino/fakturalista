<?php

namespace App\Http\Requests;

use App\Services\Tax\TaxTreatment;
use App\Rules\TaxRateTreatment;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
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
            // Drafts may still be saved without it (see InvoiceController) -
            // AEAT's DescripcionOperacion is only enforced as a hard
            // precondition at issuance time, by VerifactuChainService.
            'descripcion_operacion' => 'nullable|string|max:500',
            // Morocco Phase 1C.1 (docs/morocco-phase-1c1-generic-tax-foundation.md
            // §2): the server now recomputes every total from these fields
            // via DocumentCalculationService, so they must be genuinely
            // valid numbers rather than silently trusted. tax_rate has no
            // fixed whitelist (0/4/10/21/... and any future country's
            // rate all pass) - only a sane upper bound, matching how a
            // percentage is meaningful at all.
            'discount_rate'      => 'nullable|numeric|min:0|max:100',
            'carts.*.qty'        => 'required|numeric|min:0.001',
            'carts.*.price'      => 'required|numeric|min:0',
            'carts.*.discount'   => 'nullable|numeric|min:0|max:100',
            'carts.*.vta'        => ['nullable', 'numeric', 'min:0', 'max:100', new TaxRateTreatment],
            'carts.*.tax_treatment' => 'nullable|string|in:' . implode(',', TaxTreatment::ALL),
        ];
    }
}
