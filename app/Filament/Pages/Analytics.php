<?php

namespace App\Filament\Pages;

use App\Filament\Analytics\Widgets;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\AnalyticsTracker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Website analytics (first-party, cookieless - config/analytics.php).
 * Every widget reads the period from this page's filters.
 */
class Analytics extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'analytics';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?string $title = 'Analytics du site';
    protected static ?int $navigationSort = -1;

    public static function canAccess(): bool
    {
        return auth('admin')->check();
    }

    public function getSubheading(): ?string
    {
        return 'Mesure d\'audience interne, sans cookie : pages publiques uniquement, robots et administrateurs exclus. '
            . 'Les visiteurs uniques sont estimés jour par jour (identifiant anonyme quotidien).';
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('period')
                    ->label('Période')
                    ->options(AnalyticsPeriod::PRESETS)
                    ->default('30d')
                    ->selectablePlaceholder(false)
                    ->live(),
                Forms\Components\DatePicker::make('from')
                    ->label('Du')
                    ->maxDate(now(config('analytics.timezone')))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (Forms\Get $get) => $get('period') === 'custom'),
                Forms\Components\DatePicker::make('until')
                    ->label('Au')
                    ->maxDate(now(config('analytics.timezone')))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (Forms\Get $get) => $get('period') === 'custom'),
            ])->columns(3),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            Widgets\AnalyticsOverview::class,
            Widgets\VisitorsTrendChart::class,
            Widgets\PageviewsTrendChart::class,
            Widgets\TrafficSourcesChart::class,
            Widgets\DevicesChart::class,
            Widgets\TopCountriesTable::class,
            Widgets\SignupFunnel::class,
            Widgets\TopPagesTable::class,
            Widgets\ReferrersTable::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return ['md' => 2, 'xl' => 2];
    }
}
