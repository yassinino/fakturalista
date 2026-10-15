<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsConversion;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Locale;

/**
 * Read side of the analytics (Filament dashboard). Every figure follows the
 * definitions in config/analytics.php:
 *
 *  - pageviews:  rows
 *  - visitors:   distinct (day, daily visitor hash) - summed over days
 *  - visits:     rows flagged is_entrance
 *  - registrations: analytics_conversions of type free_trial_signup
 */
class AnalyticsReport
{
    public const SOURCE_LABELS = [
        'search'   => 'Moteurs de recherche',
        'direct'   => 'Direct / sans référent',
        'social'   => 'Réseaux sociaux',
        'referral' => 'Sites référents',
        'campaign' => 'Campagnes (UTM)',
        'ai'       => 'Assistants IA',
    ];

    public const DEVICE_LABELS = ['desktop' => 'Ordinateur', 'mobile' => 'Mobile', 'tablet' => 'Tablette'];

    private const VISITORS = 'COUNT(DISTINCT view_date, visitor_hash)';

    private function views(AnalyticsPeriod $period): Builder
    {
        return DB::connection('mysql')->table('analytics_pageviews')
            ->whereBetween('view_date', [$period->startDate(), $period->endDate()]);
    }

    /** @return array{pageviews:int, visitors:int, visits:int, registrations:int, conversion_rate:?float} */
    public function totals(AnalyticsPeriod $period): array
    {
        $row = $this->views($period)
            ->selectRaw('COUNT(*) AS pageviews, ' . self::VISITORS . ' AS visitors, COALESCE(SUM(is_entrance), 0) AS visits')
            ->first();

        $registrations = $this->registrations($period);
        $visitors      = (int) $row->visitors;

        return [
            'pageviews'       => (int) $row->pageviews,
            'visitors'        => $visitors,
            'visits'          => (int) $row->visits,
            'registrations'   => $registrations,
            'conversion_rate' => $visitors > 0 ? round($registrations / $visitors * 100, 2) : null,
        ];
    }

    public function visitors(AnalyticsPeriod $period): int
    {
        return (int) $this->views($period)->selectRaw(self::VISITORS . ' AS v')->value('v');
    }

    public function registrations(AnalyticsPeriod $period): int
    {
        return AnalyticsConversion::where('type', AnalyticsConversion::FREE_TRIAL_SIGNUP)
            ->whereBetween('conversion_date', [$period->startDate(), $period->endDate()])
            ->count();
    }

    /** @return array<string, array{pageviews:int, visitors:int, visits:int, registrations:int}> keyed by date, gaps filled */
    public function daily(AnalyticsPeriod $period): array
    {
        $rows = $this->views($period)
            ->selectRaw('view_date, COUNT(*) AS pageviews, COUNT(DISTINCT visitor_hash) AS visitors, SUM(is_entrance) AS visits')
            ->groupBy('view_date')
            ->get()
            ->keyBy(fn ($r) => (string) $r->view_date);

        $signups = AnalyticsConversion::where('type', AnalyticsConversion::FREE_TRIAL_SIGNUP)
            ->whereBetween('conversion_date', [$period->startDate(), $period->endDate()])
            ->selectRaw('conversion_date, COUNT(*) AS n')
            ->groupBy('conversion_date')
            ->pluck('n', 'conversion_date');

        $out = [];
        for ($day = $period->start; $day->lte($period->end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $row = $rows->get($key);
            $out[$key] = [
                'pageviews'     => (int) ($row->pageviews ?? 0),
                'visitors'      => (int) ($row->visitors ?? 0),
                'visits'        => (int) ($row->visits ?? 0),
                'registrations' => (int) ($signups[$key] ?? 0),
            ];
        }

        return $out;
    }

    /** Visits per country (country of the visit's first page). */
    public function countries(AnalyticsPeriod $period, int $limit = 10): array
    {
        $rows = $this->views($period)
            ->where('is_entrance', true)
            ->selectRaw('country_code, COUNT(*) AS visits')
            ->groupBy('country_code')
            ->orderByDesc('visits')
            ->get();

        $total = max(1, (int) $rows->sum('visits'));

        return $rows->take($limit)->map(fn ($r) => [
            'code'    => $r->country_code,
            'name'    => self::countryName($r->country_code),
            'visits'  => (int) $r->visits,
            'percent' => round($r->visits / $total * 100, 1),
        ])->all();
    }

    /** Most viewed pages, per language version. */
    public function pages(AnalyticsPeriod $period, int $limit = 15): array
    {
        $rows = $this->views($period)
            ->selectRaw('path, locale, MAX(title) AS title, COUNT(*) AS views, ' . self::VISITORS . ' AS visitors')
            ->groupBy('path', 'locale')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Top 3 countries of each listed page, in one query.
        $byCountry = $this->views($period)
            ->whereIn('path', $rows->pluck('path')->unique()->all())
            ->selectRaw('path, locale, country_code, COUNT(*) AS views')
            ->groupBy('path', 'locale', 'country_code')
            ->orderByDesc('views')
            ->get()
            ->groupBy(fn ($r) => $r->path . '|' . $r->locale);

        return $rows->map(function ($r) use ($byCountry) {
            $countries = collect($byCountry->get($r->path . '|' . $r->locale, []))
                ->take(3)
                ->map(fn ($c) => ($c->country_code ?: '??') . ' ' . round($c->views / max(1, $r->views) * 100) . ' %')
                ->implode(' · ');

            return [
                'path'      => $r->path,
                'locale'    => $r->locale,
                'title'     => $r->title,
                'views'     => (int) $r->views,
                'visitors'  => (int) $r->visitors,
                'countries' => $countries,
            ];
        })->all();
    }

    /** Visits per source category. */
    public function sources(AnalyticsPeriod $period): array
    {
        return $this->views($period)
            ->where('is_entrance', true)
            ->selectRaw('source, COUNT(*) AS visits')
            ->groupBy('source')
            ->orderByDesc('visits')
            ->pluck('visits', 'source')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function referrers(AnalyticsPeriod $period, int $limit = 10): array
    {
        return $this->views($period)
            ->where('is_entrance', true)
            ->whereIn('source', ['search', 'social', 'ai', 'referral'])
            ->selectRaw('source, source_name, COUNT(*) AS visits')
            ->groupBy('source', 'source_name')
            ->orderByDesc('visits')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['source' => $r->source, 'name' => $r->source_name, 'visits' => (int) $r->visits])
            ->all();
    }

    public function campaigns(AnalyticsPeriod $period, int $limit = 10): array
    {
        return $this->views($period)
            ->where('is_entrance', true)
            ->where('source', 'campaign')
            ->selectRaw('utm_source, utm_medium, utm_campaign, COUNT(*) AS visits')
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
            ->orderByDesc('visits')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** Visits per device type. */
    public function devices(AnalyticsPeriod $period): array
    {
        return $this->views($period)
            ->where('is_entrance', true)
            ->selectRaw('device, COUNT(*) AS visits')
            ->groupBy('device')
            ->pluck('visits', 'device')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** Daily-unique visitors reaching each step, then completed signups. */
    public function funnel(AnalyticsPeriod $period): array
    {
        $step = fn (?array $paths) => (int) $this->views($period)
            ->when($paths, fn ($q) => $q->whereIn('path', $paths))
            ->selectRaw(self::VISITORS . ' AS v')
            ->value('v');

        return [
            ['label' => 'Visiteurs du site', 'value' => $step(null)],
            ['label' => 'Ont vu les tarifs', 'value' => $step(['/pricing'])],
            ['label' => "Ont ouvert l'inscription", 'value' => $step(['/register'])],
            ['label' => 'Inscriptions confirmées', 'value' => $this->registrations($period)],
        ];
    }

    public static function countryName(?string $code): string
    {
        if (! $code) {
            return 'Inconnu';
        }

        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-' . $code, 'fr') : '';

        return $name && $name !== $code ? $name : $code;
    }
}
