<?php

namespace App\Filament\Analytics\Widgets;

use Filament\Widgets\Widget;

class TopPagesTable extends Widget
{
    use ReadsAnalyticsPeriod;

    protected static string $view = 'filament.analytics.pages';
    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'rows'   => $this->report()->pages($period, 20),
            'period' => $period,
        ] + $this->extraViewData($period);
    }

    protected function extraViewData($period): array
    {
        return [];
    }
}
