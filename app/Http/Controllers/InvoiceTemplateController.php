<?php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceTemplateController extends Controller
{
    public function index()
    {
        $templates = InvoiceTemplate::orderByDesc('created_at')->get();

        return response([
            'templates' => $templates,
        ], 200);
    }

    public function show(InvoiceTemplate $invoiceTemplate)
    {
        return response([
            'template' => $invoiceTemplate,
        ], 200);
    }

    public function store(Request $request)
    {
        $template = InvoiceTemplate::first();

        // Si ya existe, permitimos mismo slug ignorando su id en la validación
        $data = $this->validatedData($request, $template?->id);

        if ($template) {
            $template->fill($data);
            $template->save();
            $status = 200;
            $message = 'Plantilla actualizada correctamente.';
        } else {
            $template = InvoiceTemplate::create($data);
            $status = 201;
            $message = 'Plantilla creada correctamente.';
        }

        return response([
            'message' => $message,
            'template' => $template,
        ], $status);
    }

    public function update(Request $request, InvoiceTemplate $invoiceTemplate)
    {
        $data = $this->validatedData($request, $invoiceTemplate->id);

        $invoiceTemplate->fill($data);
        $invoiceTemplate->save();

        return response([
            'message' => 'Plantilla actualizada correctamente.',
            'template' => $invoiceTemplate,
        ], 200);
    }

    public function destroy(InvoiceTemplate $invoiceTemplate)
    {
        $invoiceTemplate->delete();

        return response([
            'message' => 'Plantilla eliminada.',
        ], 200);
    }

    protected function validatedData(Request $request, ?string $ignoreId = null): array
    {
        $rules = [
            'name' => 'required|string|max:120',
            'slug' => [
                'required',
                'string',
                'max:160',
                Rule::unique('invoice_templates')->ignore($ignoreId),
            ],
            'version' => 'nullable|integer|min:1',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'document_type' => 'nullable|string|max:30',
            'engine' => 'nullable|string|max:30',
            'paper_size' => 'nullable|string|max:10',
            'orientation' => 'nullable|string|max:12',
            'locale' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:64',
            'primary' => 'nullable|string|max:16',
            'text' => 'nullable|string|max:16',
            'muted' => 'nullable|string|max:16',
            'table_header_bg' => 'nullable|string|max:16',
            'table_header_text' => 'nullable|string|max:16',
            'table_border' => 'nullable|string|max:16',
            'font_family' => 'nullable|string|max:120',
            'font_size' => 'nullable|string|max:20',
            'logo_width_mm' => 'nullable|integer|min:10|max:300',
            'logo_position' => 'nullable|string|max:10',
            'show_payment_terms' => 'sometimes|boolean',
            'show_customer_number' => 'sometimes|boolean',
            'show_customer_phone' => 'sometimes|boolean',
            'show_shipping_address' => 'sometimes|boolean',
            'billing_address_right' => 'sometimes|boolean',
            'show_discount' => 'sometimes|boolean',
            'show_tax_column' => 'sometimes|boolean',
            'show_subtotal' => 'sometimes|boolean',
            'show_tax_breakdown' => 'sometimes|boolean',
            'bold_total' => 'sometimes|boolean',
            'payment_note' => 'nullable|string',
            'show_payment_note' => 'sometimes|boolean',
            'settings' => 'nullable|array',
            'placeholders' => 'nullable|array',
            'logo_path' => 'nullable|string|max:255',
            'signature_path' => 'nullable|string|max:255',
            'background_path' => 'nullable|string|max:255',
            'created_by' => 'nullable',
            'updated_by' => 'nullable',
        ];

        $validated = $request->validate($rules);

        // Defaults on create (so frontend can send minimal payload)
        if (!$ignoreId) {
            $validated = array_merge([
                'version' => 1,
                'document_type' => 'invoice',
                'engine' => 'dompdf',
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'locale' => 'es',
                'timezone' => 'Europe/Madrid',
            ], $validated);
        }

        // Normalize booleans when they come as strings/ints from the frontend
        $booleanKeys = [
            'is_default',
            'is_active',
            'show_payment_terms',
            'show_customer_number',
            'show_customer_phone',
            'show_shipping_address',
            'billing_address_right',
            'show_discount',
            'show_tax_column',
            'show_subtotal',
            'show_tax_breakdown',
            'bold_total',
            'show_payment_note',
        ];

        foreach ($booleanKeys as $key) {
            if ($request->has($key)) {
                $validated[$key] = filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        return $validated;
    }
}
