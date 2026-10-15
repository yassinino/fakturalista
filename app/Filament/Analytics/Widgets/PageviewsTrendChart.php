<?php

namespace App\Filament\Analytics\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class PageviewsTrendChart extends ChartWidget
{
    use ReadsAnalyticsPeriod;

    protected static ?string $heading = 'Pages vues et inscriptions par jour';
    protected static ?string $pollingInterval = null;
    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $daily = $this->report()->daily($this->period());

        return [
            'datasets' => [
                ['label' => 'Pages vues', 'data' => array_column($daily, 'pageviews'), 'backgroundColor' => 'rgba(233,30,99,.75)', 'borderRadius' => 4, 'yAxisID' => 'y'],
                ['label' => 'Inscriptions', 'data' => array_column($daily, 'registrations'), 'type' => 'line', 'borderColor' => '#16a34a', 'backgroundColor' => '#16a34a', 'yAxisID' => 'y1', 'tension' => .3],
            ],
            'labels' => array_map(fn ($d) => Carbon::parse($d)->locale('fr')->isoFormat('D MMM'), array_keys($daily)),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => [
            'y'  => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]],
        ]];
    }
}
