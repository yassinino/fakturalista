<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central "connected Stripe account -> tenant" map (Step 6A.1). Only
 * App\Services\StripeConnectService writes it; the central Connect webhook
 * reads it to find the tenant for account-level events without scanning
 * tenant databases.
 */
class StripeConnectAccount extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'stripe_account_id',
        'tenant_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
