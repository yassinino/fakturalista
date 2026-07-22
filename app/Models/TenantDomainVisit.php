<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDomainVisit extends Model
{
    // Always in the central database — this model must never switch to a tenant DB.
    // When $onFail fires, tenancy has NOT been initialized, so the default
    // connection is already the central one. The explicit assignment here
    // is a safety guard for any future context where tenancy might be active.
    protected $connection = 'mysql';

    public const UPDATED_AT = null; // visits are immutable — no updated_at column

    protected $fillable = [
        'domain',
        'subdomain',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
