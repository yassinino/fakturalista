<?php

namespace App\Filament\Analytics\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class VisitorsTrendChart extends ChartWidget
{
    use ReadsAnalyticsPeriod;

    protected static ?string $heading = 'Visiteurs et visites par jour';
    protected static ?string $pollingInterval = null;
    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = $this->report()->daily($this->period());

        return [
            'datasets' => [
                ['label' => 'Visiteurs', 'data' => array_column($daily, 'visitors'), 'borderColor' => '#E91E63', 'backgroundColor' => 'rgba(233,30,99,.12)', 'fill' => true, 'tension' => .3],
                ['label' => 'Visites', 'data' => array_column($daily, 'visits'), 'borderColor' => '#6366f1', 'backgroundColor' => 'transparent', 'tension' => .3],
            ],
            'labels' => array_map(fn ($d) => Carbon::parse($d)->locale('fr')->isoFormat('D MMM'), array_keys($daily)),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['display' => true]]];
    }
}
