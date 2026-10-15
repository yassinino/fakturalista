<?php

namespace App\Filament\Analytics\Widgets;

use App\Services\Analytics\AnalyticsReport;
use Filament\Widgets\ChartWidget;

class TrafficSourcesChart extends ChartWidget
{
    use ReadsAnalyticsPeriod;

    protected static ?string $heading = 'Sources de trafic (visites)';
    protected static ?string $pollingInterval = null;
    protected static ?string $maxHeight = '260px';

    private const COLORS = ['search' => '#2563eb', 'direct' => '#94a3b8', 'social' => '#E91E63', 'referral' => '#f59e0b', 'campaign' => '#16a34a', 'ai' => '#8b5cf6'];

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getDescription(): ?string
    {
        return '« Direct / sans référent » inclut les visites dont le navigateur ne transmet pas l\'origine.';
    }

    protected function getData(): array
    {
        $sources = $this->report()->sources($this->period());
        unset($sources['internal']);

        return [
            'datasets' => [[
                'data'            => array_values($sources),
                'backgroundColor' => array_map(fn ($k) => self::COLORS[$k] ?? '#cbd5e1', array_keys($sources)),
            ]],
            'labels' => array_map(fn ($k) => AnalyticsReport::SOURCE_LABELS[$k] ?? $k, array_keys($sources)),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['display' => false], 'y' => ['display' => false]], 'plugins' => ['legend' => ['position' => 'right']]];
    }
}
