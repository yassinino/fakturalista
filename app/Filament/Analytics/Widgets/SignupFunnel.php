<?php

namespace App\Filament\Analytics\Widgets;

use Filament\Widgets\Widget;

class SignupFunnel extends Widget
{
    use ReadsAnalyticsPeriod;

    protected static string $view = 'filament.analytics.funnel';
    protected int | string | array $columnSpan = 1;

    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'rows'   => $this->report()->funnel($period),
            'period' => $period,
        ] + $this->extraViewData($period);
    }

    protected function extraViewData($period): array
    {
        return [];
    }
}
