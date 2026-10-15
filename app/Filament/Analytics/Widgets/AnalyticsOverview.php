<?php

namespace App\Filament\Analytics\Widgets;

use App\Services\Analytics\AnalyticsPeriod;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsOverview extends StatsOverviewWidget
{
    use ReadsAnalyticsPeriod;

    protected static ?string $pollingInterval = null;
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $report  = $this->report();
        $period  = $this->period();
        $totals  = $report->totals($period);
        $before  = $report->totals($period->previous());
        $daily   = $report->daily($period);

        $trend = function (int|float $now, int|float $then): array {
            if ($then <= 0) {
                return [$now > 0 ? 'Nouveau sur la période' : 'Aucune donnée la période précédente', null, 'gray'];
            }
            $delta = ($now - $then) / $then * 100;

            return [
                sprintf('%s%s %% vs période précédente', $delta >= 0 ? '+' : '', number_format($delta, 1, ',', ' ')),
                $delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
                $delta >= 0 ? 'success' : 'danger',
            ];
        };
        $stat = function (string $label, string $value, array $trend, ?array $chart = null, ?string $color = null) {
            [$text, $icon, $trendColor] = $trend;
            $s = Stat::make($label, $value)->description($text)->color($color ?? $trendColor);
            if ($icon) {
                $s->descriptionIcon($icon);
            }
            if ($chart) {
                $s->chart($chart);
            }

            return $s;
        };

        $visitorsTrend  = array_column($daily, 'visitors');
        $pageviewsTrend = array_column($daily, 'pageviews');

        return [
            Stat::make("Visiteurs aujourd'hui", self::n($report->visitors(AnalyticsPeriod::make('today'))))
                ->description('Depuis minuit (' . config('analytics.timezone') . ')'),
            Stat::make('Visiteurs – 7 jours', self::n($report->visitors(AnalyticsPeriod::make('7d'))))
                ->description('Somme des visiteurs uniques par jour'),
            Stat::make('Visiteurs – 30 jours', self::n($report->visitors(AnalyticsPeriod::make('30d'))))
                ->description('Somme des visiteurs uniques par jour'),
            $stat('Visiteurs (période)', self::n($totals['visitors']), $trend($totals['visitors'], $before['visitors']), $visitorsTrend),
            $stat('Pages vues', self::n($totals['pageviews']), $trend($totals['pageviews'], $before['pageviews']), $pageviewsTrend),
            $stat('Visites', self::n($totals['visits']), $trend($totals['visits'], $before['visits'])),
            $stat('Inscriptions (essai gratuit)', self::n($totals['registrations']), $trend($totals['registrations'], $before['registrations'])),
            Stat::make('Taux de conversion (estimé)', $totals['conversion_rate'] === null ? '–' : number_format($totals['conversion_rate'], 2, ',', ' ') . ' %')
                ->description('Inscriptions ÷ visiteurs uniques × 100')
                ->color('primary'),
        ];
    }
}
