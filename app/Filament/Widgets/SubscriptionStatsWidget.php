<?php

namespace App\Filament\Widgets;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SubscriptionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $activeSubs = Subscription::on('mysql')
            ->whereIn('status', ['active', 'trialing'])
            ->count();

        $totalPlans = Plan::on('mysql')->where('active', true)->count();

        $mrr = Subscription::on('mysql')
            ->whereIn('status', ['active', 'trialing'])
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.monthly_price');

        $mrrFormatted = number_format($mrr / 100, 2, ',', ' ') . ' €';

        $mostPopular = Subscription::on('mysql')
            ->whereIn('status', ['active', 'trialing'])
            ->select('plan_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('plan_id')
            ->orderByDesc('cnt')
            ->with('plan')
            ->first();

        $popularName = $mostPopular?->plan
            ? (json_decode($mostPopular->plan->getRawOriginal('name'), true)['fr'] ?? $mostPopular->plan->slug)
            : '-';

        $totalTenants = Tenant::count();

        return [
            Stat::make('Abonnés actifs', $activeSubs)
                ->icon('heroicon-o-users')
                ->color('success'),

            Stat::make('MRR estimé', $mrrFormatted)
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->description('Revenus mensuels récurrents'),

            Stat::make('Plans actifs', $totalPlans)
                ->icon('heroicon-o-credit-card')
                ->color('info'),

            Stat::make('Plan le plus populaire', $popularName)
                ->icon('heroicon-o-star')
                ->color('warning'),

            Stat::make('Tenants total', $totalTenants)
                ->icon('heroicon-o-building-office')
                ->color('gray'),
        ];
    }
}
