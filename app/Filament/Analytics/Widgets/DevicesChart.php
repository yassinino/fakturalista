<?php

namespace App\Filament\Analytics\Widgets;

use App\Services\Analytics\AnalyticsReport;
use Filament\Widgets\ChartWidget;

class DevicesChart extends ChartWidget
{
    use ReadsAnalyticsPeriod;

    protected static ?string $heading = 'Appareils (visites)';
    protected static ?string $pollingInterval = null;
    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $devices = $this->report()->devices($this->period());

        return [
            'datasets' => [[
                'data'            => array_values($devices),
                'backgroundColor' => array_map(fn ($k) => ['desktop' => '#0f172a', 'mobile' => '#E91E63', 'tablet' => '#f59e0b'][$k] ?? '#cbd5e1', array_keys($devices)),
            ]],
            'labels' => array_map(fn ($k) => AnalyticsReport::DEVICE_LABELS[$k] ?? $k, array_keys($devices)),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['display' => false], 'y' => ['display' => false]], 'plugins' => ['legend' => ['position' => 'right']]];
    }
}
