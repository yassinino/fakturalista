<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Throwable;

/** Inclusive local-date range for the Analytics dashboard (analytics.timezone). */
final class AnalyticsPeriod
{
    public const PRESETS = [
        'today'     => "Aujourd'hui",
        'yesterday' => 'Hier',
        '7d'        => '7 derniers jours',
        '30d'       => '30 derniers jours',
        'custom'    => 'Période personnalisée',
    ];

    private function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $preset,
    ) {}

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('analytics.timezone'))->startOfDay();
    }

    public static function make(string $preset, ?string $from = null, ?string $until = null): self
    {
        $today = self::today();

        return match ($preset) {
            'today'     => new self($today, $today, $preset),
            'yesterday' => new self($today->subDay(), $today->subDay(), $preset),
            '7d'        => new self($today->subDays(6), $today, $preset),
            'custom'    => self::custom($from, $until, $today),
            default     => new self($today->subDays(29), $today, '30d'),
        };
    }

    public static function fromFilters(?array $filters): self
    {
        return self::make((string) ($filters['period'] ?? '30d'), $filters['from'] ?? null, $filters['until'] ?? null);
    }

    private static function custom(?string $from, ?string $until, CarbonImmutable $today): self
    {
        try {
            $start = $from ? CarbonImmutable::parse($from, config('analytics.timezone'))->startOfDay() : $today->subDays(29);
            $end   = $until ? CarbonImmutable::parse($until, config('analytics.timezone'))->startOfDay() : $today;
        } catch (Throwable) {
            return self::make('30d');
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }
        // No future days: clamp both ends after ordering them.
        $end   = $end->gt($today) ? $today : $end;
        $start = $start->gt($end) ? $end : $start;
        if ($start->diffInDays($end) > 366) {
            $start = $end->subDays(366);
        }

        return new self($start, $end, 'custom');
    }

    /** Same length, immediately before - for "vs période précédente". */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->start->subDays($days), $this->start->subDay(), $this->preset);
    }

    public function days(): int
    {
        return $this->start->diffInDays($this->end) + 1;
    }

    public function startDate(): string
    {
        return $this->start->toDateString();
    }

    public function endDate(): string
    {
        return $this->end->toDateString();
    }

    public function label(): string
    {
        return $this->start->equalTo($this->end)
            ? $this->start->locale('fr')->isoFormat('D MMM YYYY')
            : $this->start->locale('fr')->isoFormat('D MMM') . ' – ' . $this->end->locale('fr')->isoFormat('D MMM YYYY');
    }
}
