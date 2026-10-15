<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /a/collect - page-view beacon from partials/analytics-tracker.
 * Always answers 204 (even when the event is ignored), so it reveals
 * nothing to a client probing it and never breaks anything.
 */
class AnalyticsBeaconController extends Controller
{
    public function __invoke(Request $request, AnalyticsTracker $tracker): Response
    {
        $raw = $request->getContent();

        if ($raw !== '' && strlen($raw) <= 4096) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $tracker->recordPageview($request, $data);
            }
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
