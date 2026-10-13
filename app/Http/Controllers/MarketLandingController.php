<?php

namespace App\Http\Controllers;

use App\Support\MarketLandingPages;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Market SEO landing pages (/ma/{slug}) - see App\Support\MarketLandingPages.
 * Server-rendered, French by default, Arabic at ?lang=ar.
 */
class MarketLandingController extends Controller
{
    public function show(Request $request): View
    {
        // Read by name: the market comes from a route default, which Laravel
        // would otherwise pass positionally after {slug}.
        $page = MarketLandingPages::find((string) $request->route('market'), (string) $request->route('slug')) ?? abort(404);

        // A page only exists in its market's languages; any other session
        // language (en, es) gets the default version, which is also what
        // the canonical URL points to.
        if (! in_array(app()->getLocale(), $page['locales'], true)) {
            App::setLocale($page['locales'][0]);
        }

        $trialDays = Seo::trialDays();
        $content   = trans('landing.' . $page['key']);
        $common    = trans('landing.common');

        array_walk_recursive($content, function (&$value) use ($trialDays) {
            if (is_string($value)) {
                $value = str_replace(':days', (string) $trialDays, $value);
            }
        });

        return view('landing.show', [
            'page'      => $page,
            'content'   => $content,
            'common'    => $common,
            'locale'    => app()->getLocale(),
            'trialDays' => $trialDays,
        ]);
    }
}
