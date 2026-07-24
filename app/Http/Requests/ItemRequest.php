<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'family_id' => 'required',
            'type' => 'required|in:1,2',
            'sales_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'vta' => 'nullable|numeric|min:0|max:100',
            'currency' => 'nullable|string|size:3',
            'reference' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ];
    }
}
