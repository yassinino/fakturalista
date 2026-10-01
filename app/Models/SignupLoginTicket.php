<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Central, one-time post-registration login ticket - see
 * App\Services\Auth\SignupLoginTicketService (the only writer/reader).
 */
class SignupLoginTicket extends Model
{
    protected $connection = 'mysql';

    protected $fillable = ['token_hash', 'tenant_id', 'user_id', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}
