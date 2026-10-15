<?php

namespace Tests\Feature\Analytics;

use App\Filament\Analytics\Widgets\AnalyticsOverview;
use App\Filament\Analytics\Widgets\TopPagesTable;
use App\Filament\Pages\Analytics;
use App\Models\AdminUser;
use App\Models\AnalyticsConversion;
use App\Models\AnalyticsPageview;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\AnalyticsReport;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Analytics read side: aggregation definitions, date filtering, dashboard
 * access. Uses its own fixed past week (1-7 July 2026) so exact figures can
 * be asserted; every row it creates is removed afterwards.
 */
class AnalyticsDashboardTest extends TestCase
{
    private const FROM = '2026-07-01';
    private const UNTIL = '2026-07-07';

    private int $maxView;
    private int $maxConversion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maxView = (int) AnalyticsPageview::max('id');
        $this->maxConversion = (int) AnalyticsConversion::max('id');
        Filament::setCurrentPanel(Filament::getPanel('backoffice'));
        $this->seedWeek();
    }

    protected function tearDown(): void
    {
        AnalyticsPageview::where('id', '>', $this->maxView)->delete();
        AnalyticsConversion::where('id', '>', $this->maxConversion)->delete();
        parent::tearDown();
    }

    private function pageview(string $date, string $visitor, string $path, array $extra = []): void
    {
        AnalyticsPageview::create(array_merge([
            'viewed_at' => CarbonImmutable::parse($date . ' 10:00:00'), 'view_date' => $date,
            'visitor_hash' => str_pad($visitor, 32, '0'), 'is_entrance' => false, 'path' => $path,
            'title' => $path === '/' ? 'Accueil' : ucfirst(trim($path, '/')), 'locale' => 'fr',
            'country_code' => 'MA', 'device' => 'desktop', 'source' => 'internal',
        ], $extra));
    }

    /**
     * 1 July: visitor A (google, MA, mobile) views / then /pricing then /register;
     *         visitor B (direct, FR) views /.
     * 2 July: visitor A again (new daily id in reality - here "a2") views /pricing (linkedin);
     *         visitor C (unknown country, referral) views / in ar.
     * Conversions: one on 1 July (from A), one on 5 July.
     */
    private function seedWeek(): void
    {
        $this->pageview('2026-07-01', 'a1', '/', ['is_entrance' => true, 'source' => 'search', 'source_name' => 'google', 'device' => 'mobile']);
        $this->pageview('2026-07-01', 'a1', '/pricing', ['device' => 'mobile']);
        $this->pageview('2026-07-01', 'a1', '/register', ['device' => 'mobile']);
        $this->pageview('2026-07-01', 'b1', '/', ['is_entrance' => true, 'source' => 'direct', 'country_code' => 'FR']);
        $this->pageview('2026-07-02', 'a2', '/pricing', ['is_entrance' => true, 'source' => 'social', 'source_name' => 'linkedin']);
        $this->pageview('2026-07-02', 'c2', '/', ['is_entrance' => true, 'source' => 'referral', 'source_name' => 'example.org', 'country_code' => null, 'locale' => 'ar']);

        foreach (['2026-07-01', '2026-07-05'] as $day) {
            AnalyticsConversion::create(['type' => AnalyticsConversion::FREE_TRIAL_SIGNUP, 'converted_at' => $day . ' 12:00:00', 'conversion_date' => $day]);
        }
    }

    private function week(): AnalyticsPeriod
    {
        return AnalyticsPeriod::make('custom', self::FROM, self::UNTIL);
    }

    // ── Aggregation ─────────────────────────────────────────────────────

    public function test_totals_follow_the_documented_definitions(): void
    {
        $totals = app(AnalyticsReport::class)->totals($this->week());

        $this->assertSame(6, $totals['pageviews']);
        $this->assertSame(4, $totals['visitors'], 'Daily-unique visitors summed over days: a1, b1, a2, c2.');
        $this->assertSame(4, $totals['visits']);
        $this->assertSame(2, $totals['registrations']);
        $this->assertSame(50.0, $totals['conversion_rate']);
    }

    public function test_breakdowns_are_accurate(): void
    {
        $report = app(AnalyticsReport::class);
        $week = $this->week();

        $sources = $report->sources($week);
        ksort($sources);
        $this->assertSame(['direct' => 1, 'referral' => 1, 'search' => 1, 'social' => 1], $sources);
        $this->assertSame(['mobile' => 1, 'desktop' => 3], array_merge(['mobile' => 0], $report->devices($week)));

        $countries = collect($report->countries($week))->keyBy(fn ($c) => $c['code'] ?? 'unknown');
        $this->assertSame(2, $countries['MA']['visits']);
        $this->assertSame(50.0, $countries['MA']['percent']);
        $this->assertSame('Maroc', $countries['MA']['name']);
        $this->assertSame('Inconnu', $countries['unknown']['name']);

        $pages = collect($report->pages($week))->keyBy(fn ($p) => $p['path'] . '|' . $p['locale']);
        $this->assertSame(2, $pages['/|fr']['views']);
        $this->assertSame(1, $pages['/|ar']['views'], 'Each language version is listed separately.');
        $this->assertSame(2, $pages['/pricing|fr']['visitors']);
        $this->assertStringContainsString('MA 100 %', $pages['/pricing|fr']['countries']);

        $funnel = array_column($report->funnel($week), 'value');
        $this->assertSame([4, 2, 1, 2], $funnel);

        $daily = $report->daily($week);
        $this->assertCount(7, $daily, 'Days without data are filled with zeros.');
        $this->assertSame(['pageviews' => 4, 'visitors' => 2, 'visits' => 2, 'registrations' => 1], $daily['2026-07-01']);
        $this->assertSame(0, $daily['2026-07-03']['pageviews']);
    }

    public function test_date_filtering_restricts_every_figure(): void
    {
        $report = app(AnalyticsReport::class);

        $first = $report->totals(AnalyticsPeriod::make('custom', '2026-07-01', '2026-07-01'));
        $this->assertSame([4, 2, 1], [$first['pageviews'], $first['visitors'], $first['registrations']]);

        $second = $report->totals(AnalyticsPeriod::make('custom', '2026-07-02', '2026-07-02'));
        $this->assertSame([2, 2, 0], [$second['pageviews'], $second['visitors'], $second['registrations']]);
    }

    public function test_period_presets(): void
    {
        $today = AnalyticsPeriod::today();

        $this->assertSame([$today->toDateString(), $today->toDateString()], [AnalyticsPeriod::make('today')->startDate(), AnalyticsPeriod::make('today')->endDate()]);
        $this->assertSame($today->subDay()->toDateString(), AnalyticsPeriod::make('yesterday')->startDate());
        $this->assertSame(7, AnalyticsPeriod::make('7d')->days());
        $this->assertSame(30, AnalyticsPeriod::make('30d')->days());
        $this->assertSame(30, AnalyticsPeriod::make('nonsense')->days());

        // Reversed or future custom dates are corrected, never trusted blindly.
        $custom = AnalyticsPeriod::make('custom', $today->addDays(5)->toDateString(), $today->subDays(2)->toDateString());
        $this->assertSame([$today->subDays(2)->toDateString(), $today->toDateString()], [$custom->startDate(), $custom->endDate()]);
    }

    // ── Dashboard ───────────────────────────────────────────────────────

    public function test_only_backoffice_administrators_can_open_the_dashboard(): void
    {
        $this->get('http://fakturalista.test/backoffice/analytics')->assertRedirect();

        $admin = AdminUser::create(['name' => 'A', 'email' => 'dash-' . uniqid() . '@example.test', 'password' => 'x-password-1']);
        try {
            $this->actingAs($admin, 'admin')
                ->get('http://fakturalista.test/backoffice/analytics')
                ->assertOk()
                ->assertSee('Analytics du site')
                ->assertSee('Période');
        } finally {
            $admin->delete();
        }

        // Not reachable from a tenant (api guard) session either: no route outside /backoffice.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('analytics.dashboard'));
    }

    public function test_widgets_update_when_the_period_filter_changes(): void
    {
        $admin = AdminUser::create(['name' => 'A', 'email' => 'dash-' . uniqid() . '@example.test', 'password' => 'x-password-1']);
        try {
            $this->actingAs($admin, 'admin');

            Livewire::test(Analytics::class)->assertOk()->assertSet('filters.period', '30d');

            $week = ['period' => 'custom', 'from' => self::FROM, 'until' => self::UNTIL];
            $day = ['period' => 'custom', 'from' => '2026-07-02', 'until' => '2026-07-02'];

            $html = Livewire::test(AnalyticsOverview::class, ['filters' => $week])->html();
            $this->assertMatchesRegularExpression('~Pages vues.*?>\s*6\s*<~s', $html);
            $this->assertMatchesRegularExpression('~Taux de conversion \(estimé\).*?50,00 %~s', $html);

            $html = Livewire::test(AnalyticsOverview::class, ['filters' => $day])->html();
            $this->assertMatchesRegularExpression('~Pages vues.*?>\s*2\s*<~s', $html);

            Livewire::test(TopPagesTable::class, ['filters' => $week])->assertSee('/pricing')->assertSee('MA 100 %');
            Livewire::test(TopPagesTable::class, ['filters' => ['period' => 'custom', 'from' => '2026-07-03', 'until' => '2026-07-04']])
                ->assertSee('Aucune page vue sur la période.');
        } finally {
            $admin->delete();
        }
    }
}
