<?php

namespace App\Models\Passport;

use Laravel\Passport\Token as PassportToken;

class Token extends PassportToken
{
    protected $primaryKey = 'token_id';
    public $incrementing = false;
    protected $keyType = 'string';
}