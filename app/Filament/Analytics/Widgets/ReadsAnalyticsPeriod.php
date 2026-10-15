<?php

namespace App\Filament\Analytics\Widgets;

use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\AnalyticsReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Shared by the Analytics page widgets: period from the page filters. */
trait ReadsAnalyticsPeriod
{
    use InteractsWithPageFilters;

    protected function period(): AnalyticsPeriod
    {
        return AnalyticsPeriod::fromFilters($this->filters);
    }

    protected function report(): AnalyticsReport
    {
        return app(AnalyticsReport::class);
    }

    protected static function n(int|float $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    public static function canView(): bool
    {
        return auth('admin')->check();
    }
}
