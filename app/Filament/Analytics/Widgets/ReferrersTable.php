<?php

namespace App\Filament\Analytics\Widgets;

use Filament\Widgets\Widget;

class ReferrersTable extends Widget
{
    use ReadsAnalyticsPeriod;

    protected static string $view = 'filament.analytics.referrers';
    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'rows'   => $this->report()->referrers($period, 10),
            'period' => $period,
        ] + $this->extraViewData($period);
    }

    protected function extraViewData($period): array
    {
        return ['campaigns' => $this->report()->campaigns($period, 10)];
    }
}
