<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantNotFoundController extends Controller
{
    public function show(Request $request): View
    {
        $domain    = (string) $request->query('domain', '');
        $parts     = $domain ? explode('.', $domain) : [];
        $subdomain = count($parts) > 2 ? $parts[0] : '';

        return view('errors.tenant-not-found', [
            'domain'    => $domain,
            'subdomain' => $subdomain,
        ]);
    }
}
