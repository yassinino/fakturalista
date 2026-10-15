<?php

namespace App\Filament\Analytics\Widgets;

use Filament\Widgets\Widget;

class TopCountriesTable extends Widget
{
    use ReadsAnalyticsPeriod;

    protected static string $view = 'filament.analytics.countries';
    protected int | string | array $columnSpan = 1;

    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'rows'   => $this->report()->countries($period, 12),
            'period' => $period,
        ] + $this->extraViewData($period);
    }

    protected function extraViewData($period): array
    {
        return [];
    }
}
