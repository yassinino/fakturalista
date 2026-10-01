<?php

namespace App\Services\Auth;

use App\Models\SignupLoginTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Auto-login right after self-service registration.
 *
 * Registration (central domain) calls issue() once the tenant and its
 * owner user exist; the browser is sent to the tenant's /admin/login with
 * the ticket in the URL *fragment* (never sent to any server, never in a
 * Referer header). The tenant's login page posts it back once to
 * AuthController::exchangeSignupTicket(), which calls redeem() and hands
 * out an ordinary Passport token - the same one /login issues.
 *
 * A ticket is: 64 random characters (only its SHA-256 stored), bound to one
 * tenant and one user, valid for TTL_SECONDS, usable exactly once.
 * Anything else (wrong tenant, expired, reused, unknown) redeems to null.
 */
class SignupLoginTicketService
{
    public const TTL_SECONDS = 120;

    public function issue(Tenant $tenant, string $email): ?string
    {
        $userId = $tenant->run(fn () => User::where('email', $email)->value('id'));

        if (!$userId) {
            return null;
        }

        $raw = Str::random(64);

        SignupLoginTicket::create([
            'token_hash' => hash('sha256', $raw),
            'tenant_id'  => $tenant->getTenantKey(),
            'user_id'    => $userId,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return $raw;
    }

    /** Must run inside the tenant the ticket was issued for. */
    public function redeem(string $raw): ?User
    {
        $tenantId = tenancy()->tenant?->getTenantKey();

        if (!$tenantId || strlen($raw) !== 64) {
            return null;
        }

        $ticket = SignupLoginTicket::where('token_hash', hash('sha256', $raw))
            ->where('tenant_id', $tenantId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$ticket) {
            return null;
        }

        // Single use, even under concurrent requests: only the request that
        // flips used_at gets the user.
        $claimed = SignupLoginTicket::whereKey($ticket->id)->whereNull('used_at')->update(['used_at' => now()]);

        return $claimed === 1 ? User::find($ticket->user_id) : null;
    }
}
