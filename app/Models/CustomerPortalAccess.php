<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One issued Client Portal access token for a customer - see
 * App\Services\ClientPortal\ClientPortalService, the only place that
 * creates, resolves, revokes or regenerates these. `token_hash` is a
 * SHA-256 digest; the raw token is never persisted anywhere.
 */
class CustomerPortalAccess extends Model
{
    protected $table = 'customer_portal_access';

    protected $fillable = [
        'customer_id',
        'token_hash',
        'last_accessed_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'revoked_at'       => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
