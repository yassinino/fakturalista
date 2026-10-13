<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Dynamic robots.txt (replaces the static public/robots.txt, which was
 * served identically on every tenant subdomain).
 */
class RobotsController extends Controller
{
    public function central(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /backoffice',
            'Disallow: /livewire/',
            'Disallow: /stripe/',
            'Disallow: /locale',
            'Disallow: /feedback',
            '',
            'Sitemap: ' . config('seo.canonical_url') . '/sitemap.xml',
        ];

        return $this->text($lines);
    }

    /** Tenant workspaces, client portals and payment pages are private. */
    public function tenant(): Response
    {
        return $this->text(['User-agent: *', 'Disallow: /']);
    }

    private function text(array $lines): Response
    {
        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
