<?php

namespace App\Http\Controllers;

use App\Services\Tax\TaxPresetService;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;

/**
 * Morocco Phase 1C.2 (docs/morocco-phase-1c2-tax-configuration.md §1/§2).
 * The one central source Invoice/Quote/Item Vue forms fetch the current
 * tenant's tax options from - country resolved via the existing
 * TenantContextService, never re-derived here.
 */
class TaxPresetController extends Controller
{
    public function index(TaxPresetService $presets, TenantContextService $tenantContext): JsonResponse
    {
        $country = $tenantContext->country();
        $options = $presets->forTenant();
        $default = $presets->defaultForTenant();

        return response()->json([
            'country' => $country,
            'tax_name' => $presets->taxName($country),
            'presets' => array_map(fn ($preset) => $preset->toArray() + [
                'label' => $presets->label($country, $preset->rate, $preset->treatment),
            ], $options),
            'default_code' => $default?->code,
        ]);
    }
}
