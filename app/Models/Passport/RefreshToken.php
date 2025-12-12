<?php

namespace App\Models\Passport;

use Laravel\Passport\RefreshToken as BaseRefreshToken;

class RefreshToken extends BaseRefreshToken
{
    protected $connection = 'mysql';
}