<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        ], 200);
    }

    /**
     * Actualiza el perfil de empresa y gestiona los ficheros de logo/sello.
     */
    public function update(Request $request)
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
        ], 200);
    }

    /**
     * Obtiene o crea el perfil de empresa con valores iniciales.
     */
    protected function getProfile(): CompanyProfile
    {
        return CompanyProfile::first();
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
