<?php

namespace App\Services\ClientPortal;

use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use Illuminate\Support\Str;

/**
 * All token/security logic for the Client Portal lives here, never in a
 * controller - see docs on GET /portal/{token} (routes/tenant.php,
 * App\Http\Controllers\ClientPortalController).
 *
 * Token design: a high-entropy random string (Str::random(), backed by
 * random_bytes() - cryptographically secure) is the only thing ever
 * given to a customer, embedded in the portal URL. Fakturalista never
 * stores it: only its SHA-256 digest (CustomerPortalAccess::$token_hash)
 * is persisted, and a lookup re-hashes the incoming token and matches by
 * exact equality. SHA-256 (not a slow/salted hash like bcrypt) is the
 * right tool here specifically because the input is already a random
 * 64-character secret, not a low-entropy human-chosen password - the
 * same reasoning Laravel Sanctum's own personal access tokens use.
 *
 * The token carries no customer identifier of any kind (not the
 * customer's id, not its uuid) - resolving one is a single indexed
 * lookup by token_hash, nothing else.
 */
class ClientPortalService
{
    private const TOKEN_LENGTH = 64;

    /**
     * Issue a new portal access token for $customer. Returns the raw
     * token - this is the only moment it ever exists outside the
     * customer's own browser/inbox; only its hash is persisted.
     */
    public function createAccess(Customer $customer): string
    {
        $token = Str::random(self::TOKEN_LENGTH);

        CustomerPortalAccess::create([
            'customer_id' => $customer->id,
            'token_hash'  => $this->hash($token),
        ]);

        return $token;
    }

    /**
     * Look up the access record for a raw token, if any - regardless of
     * whether it has been revoked (callers that need to tell "never
     * existed" apart from "revoked" - e.g. to answer with 404 vs 410 -
     * check CustomerPortalAccess::isRevoked() on the result themselves).
     * Never throws on a malformed/empty token; simply resolves to null.
     */
    public function resolveAccess(string $token): ?CustomerPortalAccess
    {
        if ($token === '') {
            return null;
        }

        return CustomerPortalAccess::where('token_hash', $this->hash($token))->first();
    }

    /**
     * Record that $access was just successfully used. Only ever called
     * by a caller that has already confirmed the access is not revoked -
     * this method itself does not re-check that.
     */
    public function recordAccess(CustomerPortalAccess $access): void
    {
        $access->forceFill(['last_accessed_at' => now()])->save();
    }

    /**
     * Revoke every currently-active (non-revoked) access token for
     * $customer. Revoked rows are kept, not deleted, as an audit trail.
     */
    public function revoke(Customer $customer): void
    {
        CustomerPortalAccess::where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Revoke any existing access for $customer and issue a fresh one in
     * its place. Returns the new raw token.
     */
    public function regenerateAccess(Customer $customer): string
    {
        $this->revoke($customer);

        return $this->createAccess($customer);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
