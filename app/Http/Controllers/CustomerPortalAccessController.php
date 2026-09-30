<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Services\ClientPortal\ClientPortalService;
use Illuminate\Http\JsonResponse;

/**
 * Admin side of the Client Portal link for one customer (customer edit
 * page). A thin layer over the existing ClientPortalService - no second
 * token system:
 *  - the raw token exists only in the response to store()/regenerate(),
 *    as part of the portal URL; only its SHA-256 hash is ever stored;
 *  - show() never returns a token, a hash or a URL (it can't: the raw
 *    token is gone) - only whether access is active and when it was used;
 *  - regenerate() revokes every previous link first; destroy() revokes.
 *
 * Customers are bound by uuid inside the current tenant's own database,
 * so another tenant's (or a deleted) customer is simply a 404.
 */
class CustomerPortalAccessController extends Controller
{
    public function __construct(private ClientPortalService $portal)
    {
    }

    /** GET /customers/{customer}/portal-access */
    public function show(Customer $customer): JsonResponse
    {
        return response()->json($this->status($customer));
    }

    /** POST /customers/{customer}/portal-access - first link only. */
    public function store(Customer $customer): JsonResponse
    {
        if ($this->activeAccess($customer)) {
            return response()->json(['message' => __('customer.portal.already_active')], 409);
        }

        $token = $this->portal->createAccess($customer);

        return $this->withUrl($customer, $token, __('customer.portal.created'), 201);
    }

    /** POST /customers/{customer}/portal-access/regenerate - old link(s) stop working. */
    public function regenerate(Customer $customer): JsonResponse
    {
        $token = $this->portal->regenerateAccess($customer);

        return $this->withUrl($customer, $token, __('customer.portal.regenerated'));
    }

    /** DELETE /customers/{customer}/portal-access - every link stops working. */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->portal->revoke($customer);

        return response()->json(['message' => __('customer.portal.revoked')] + $this->status($customer));
    }

    private function withUrl(Customer $customer, string $token, string $message, int $status = 200): JsonResponse
    {
        return response()
            ->json([
                'message' => $message,
                // Shown once: the raw token is not recoverable after this response.
                'url'     => request()->getSchemeAndHttpHost() . '/portal/' . $token,
            ] + $this->status($customer), $status)
            ->header('Cache-Control', 'no-store, private');
    }

    private function status(Customer $customer): array
    {
        $access = $this->activeAccess($customer);

        return [
            'active'           => (bool) $access,
            'created_at'       => $access?->created_at?->toIso8601String(),
            'last_accessed_at' => $access?->last_accessed_at?->toIso8601String(),
        ];
    }

    private function activeAccess(Customer $customer): ?CustomerPortalAccess
    {
        return CustomerPortalAccess::where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }
}
