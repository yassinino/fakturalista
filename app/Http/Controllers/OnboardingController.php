<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Country;
use App\Services\Tax\TaxPresetService;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    /**
     * Return current profile data so the wizard can pre-fill any partial data.
     */
    public function show(): JsonResponse
    {
        $profile = CompanyProfile::first();
        // Deliberately NOT ensureCompanyProfile() here: show() runs before
        // the tenant has submitted anything, and creating a row on a mere
        // read would beat store() to it. store() is the single place a
        // fresh profile gets created.

        return response()->json([
            'profile'              => $profile,
            'onboarding_completed' => $profile?->onboarding_completed_at !== null,
            // So the wizard can show ICE (Morocco) vs NIF/VAT (Spain)
            // before any CompanyProfile row exists yet - see
            // TenantContextService's authority order (Phase 1A) and
            // docs/morocco-phase-1b-identity.md §6.
            'country_code'         => app(TenantContextService::class)->country(),
            'company_context'      => app(TenantContextService::class)->toArray(),
            'country_defaults'     => [
                'MA' => TenantContextService::defaultsForCountry('MA'),
                'ES' => TenantContextService::defaultsForCountry('ES'),
            ],
        ]);
    }

    /**
     * Submit the onboarding wizard.
     * Saves company fields, marks onboarding complete, and starts the free trial
     * on the central Tenant record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Mandatory
            'owner_name'   => 'required|string|max:255',
            'trade_name'   => 'required|string|max:255',
            'address_line1'=> 'required|string|max:255',
            'city'         => 'required|string|max:255',
            'postal_code'  => 'required|string|max:20',
            'country'      => 'required|string|max:100',
            'country_code' => 'nullable|string|size:2|exists:countries,code',
            'currency'     => 'required|string|size:3',
            // Optional
            'legal_name'   => 'nullable|string|max:255',
            'tax_id'       => 'nullable|string|max:100',
            'vat_number'   => 'nullable|string|max:100',
            // Morocco Phase 1B (docs/morocco-phase-1b-identity.md §6) -
            // ICE is the recommended first-run field for a Moroccan
            // tenant, but never required (IF/RC are completed later in
            // Settings, not collected here).
            'ice'          => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:50',
            'logo'         => 'nullable|image|max:2048',
        ]);

        $profile = app(TenantContextService::class)->ensureCompanyProfile();

        // Guard: if already completed, return success without changing anything.
        if ($profile->onboarding_completed_at !== null) {
            return response()->json([
                'message'              => 'Onboarding already completed.',
                'onboarding_completed' => true,
            ]);
        }

        // country_code/locale/timezone are NOT form fields here - they were
        // already set correctly at provisioning time on the central Tenant
        // record (Filament wizard), and ensureCompanyProfile() seeded this
        // row from them. Re-asserting them here is defense-in-depth only,
        // covering the case where a CompanyProfile row was created earlier
        // by another entry point (e.g. Stripe Connect) before this form was
        // ever submitted. 'country' (free text) and 'currency' ARE explicit
        // choices made in this form, so those always win.
        $tenant = tenancy()->tenant;
        $country = $validated['country_code'] ?? ($tenant?->country ?: $profile->country_code);
        $countryChanged = $country !== $profile->country_code;
        $defaults = TenantContextService::defaultsForCountry($country);

        $profileData = [
            'trade_name'   => $validated['trade_name'],
            'legal_name'   => $validated['legal_name'] ?? $validated['trade_name'],
            'address_line1'=> $validated['address_line1'],
            'city'         => $validated['city'],
            'postal_code'  => $validated['postal_code'],
            'country'      => Country::where('code', $country)->value('name') ?? $validated['country'],
            'country_code' => $country,
            'currency'     => strtoupper($validated['currency']),
            'locale'       => $countryChanged ? $defaults['locale'] : ($tenant?->language ?: $profile->locale),
            'timezone'     => $countryChanged ? $defaults['timezone'] : ($tenant?->timezone ?: $profile->timezone),
            'default_tax_code' => $countryChanged
                ? app(TaxPresetService::class)->getDefaultForCountry($country)?->code
                : $profile->default_tax_code,
            'tax_id'       => $validated['tax_id'] ?? null,
            'vat_number'   => $validated['vat_number'] ?? null,
            'ice'          => isset($validated['ice']) ? trim($validated['ice']) : null,
            'phone'        => $validated['phone'] ?? null,
            'onboarding_completed_at' => now(),
        ];

        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $profileData['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $profile->update($profileData);

        // Also store the owner's full name on their User record.
        if (!empty($validated['owner_name'])) {
            $request->user()?->update(['name' => $validated['owner_name']]);
        }

        // Start the free trial on the central Tenant record.
        $tenant = tenancy()->tenant;
        $tenant->update([
            'country'             => $profile->country_code,
            'currency'            => $profile->currency,
            'language'            => $profile->locale,
            'timezone'            => $profile->timezone,
            'subscription_status' => 'trialing',
            'trial_ends_at'       => now()->addDays(config('billing.trial_days')),
        ]);

        Log::info('Onboarding completed', [
            'tenant_id'      => $tenant->id,
            'trial_ends_at'  => $tenant->trial_ends_at,
        ]);

        return response()->json([
            'message'              => 'Setup complete. Your free trial has started.',
            'onboarding_completed' => true,
            'trial_ends_at'        => $tenant->trial_ends_at,
        ]);
    }
}
