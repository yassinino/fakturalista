<?php

namespace App\Http\Requests;

use App\Services\Tax\TaxTreatment;
use App\Rules\TaxRateTreatment;
use App\Models\Item;
use App\Services\Tax\TaxPresetService;
use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $item = $this->route('item');
        $default = $item instanceof Item ? null : app(TaxPresetService::class)->defaultForTenant();
        $treatment = $this->tax_treatment ?? ($item instanceof Item
            ? $item->tax_treatment
            : ($this->vta === null ? $default?->treatment : TaxTreatment::TAXABLE));
        $this->merge([
            'tax_treatment' => $treatment ?? TaxTreatment::TAXABLE,
            'vta' => $this->vta ?? ($item instanceof Item ? $item->vta :
                (in_array($treatment, [TaxTreatment::EXEMPT, TaxTreatment::OUT_OF_SCOPE], true) ? 0 : ($default?->rate ?? 0))),
        ]);
    }

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
            'name' => 'required|string|max:255',
            'family_id' => 'required',
            'type' => 'required|in:1,2',
            'sales_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'vta' => ['nullable', 'numeric', 'min:0', 'max:100', new TaxRateTreatment],
            'tax_treatment' => 'nullable|string|in:' . implode(',', TaxTreatment::ALL),
            'currency' => 'nullable|string|size:3',
            'reference' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ];
    }
}
