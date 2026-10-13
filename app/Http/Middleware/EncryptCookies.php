<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Set by Google Analytics in the browser (not by Laravel); read as-is
        // for the server-side sign_up conversion (GoogleAnalyticsConversion).
        '_ga',
    ];
}
