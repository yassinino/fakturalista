<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Country;
use App\Services\TenantContextService;
use App\Services\Tax\TaxPresetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyProfileController extends Controller
{
    /**
     * Devuelve el perfil de empresa (crea uno con valores por defecto si no existe).
     */
    public function show(Request $request)
    {
        $profile = $this->getProfile();

        return response([
            'settings' => $this->formatProfile($profile),
            'company_context' => app(TenantContextService::class)->toArray(),
        ], 200);
    }

    /**
     * Actualiza el perfil de empresa y gestiona los ficheros de logo/sello.
     */
    public function update(Request $request, TaxPresetService $taxPresets)
    {
        $profile = $this->getProfile();

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'country_code' => 'required|string|size:2',
            'tax_id' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            // Morocco Phase 1B (docs/morocco-phase-1b-identity.md) - no
            // format/checksum validation, per the explicit instruction not
            // to guess unverified Moroccan rules.
            'ice' => 'nullable|string|max:255',
            'if_number' => 'nullable|string|max:255',
            // Morocco Phase 1C.2 (§11) - a convenience default only; must
            // be a real preset for the SUBMITTED country_code, never
            // enforced beyond that (it never affects an existing document -
            // see DocumentCalculationService, which never reads it).
            'default_tax_code' => ['nullable', 'string', Rule::in(
                array_map(fn ($p) => $p->code, $taxPresets->getForCountry($request->input('country_code', '')))
            )],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'brand_color' => 'nullable|string|max:16',
            'invoice_footer_note' => 'nullable|string|max:1000',
            'invoice_prefix' => 'required|string|max:20',
            'invoice_next_number' => 'required|integer|min:1',
            'invoice_number_format' => 'required|string|max:60',
            'timezone' => 'nullable|string|max:255',
            'locale' => 'nullable|string|max:10',
            'currency' => 'nullable|string|max:3',
            'bank_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'swift' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'stamp' => 'nullable|image|max:2048',
        ]);

        // Trim identifier fields - accidental leading/trailing whitespace
        // on a fiscal identifier can silently break lookups/display later.
        // Morocco Phase 1B (docs/morocco-phase-1b-identity.md §2).
        foreach (['ice', 'if_number', 'registration_number', 'tax_id'] as $identifierField) {
            if (!empty($validated[$identifierField])) {
                $validated[$identifierField] = trim($validated[$identifierField]);
            }
        }

        $validated['country_code'] = strtoupper($validated['country_code']);
        $validated['country'] = Country::where('code', $validated['country_code'])->value('name')
            ?? ($validated['country'] ?? $profile->country);
        if ($validated['country_code'] !== $profile->country_code && !$request->filled('default_tax_code')) {
            $validated['default_tax_code'] = null;
        }

        // Actualizar campos simples
        $profile->fill($validated);

        // Logo
        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $logoPath = $request->file('logo')->store('company', 'public');
            $profile->logo_path = $logoPath;
        }

        // Sello / firma
        if ($request->hasFile('stamp')) {
            if ($profile->stamp_path) {
                Storage::disk('public')->delete($profile->stamp_path);
            }
            $stampPath = $request->file('stamp')->store('company', 'public');
            $profile->stamp_path = $stampPath;
        }

        $profile->save();

        return response([
            'message' => 'Ajustes guardados correctamente.',
            'settings' => $this->formatProfile($profile),
            'company_context' => app(TenantContextService::class)->toArray(),
        ], 200);
    }

    /**
     * Obtiene o crea el perfil de empresa con valores iniciales.
     */
    protected function getProfile(): CompanyProfile
    {
        return app(\App\Services\TenantContextService::class)->ensureCompanyProfile();
    }

    /**
     * Devuelve los datos listos para el frontend (con URLs de ficheros).
     */
    protected function formatProfile(CompanyProfile $profile): array
    {
        $data = $profile->toArray();

        $data['logo_path'] = $profile->logo_path
            ? Storage::url($profile->logo_path)
            : null;

        $data['stamp_path'] = $profile->stamp_path
            ? Storage::url($profile->stamp_path)
            : null;

        return $data;
    }
}
