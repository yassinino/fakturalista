<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backend aggregation for the Reports page (Settings/Sidebar > Reports).
 * Every number here is computed with SQL aggregates (sum/count/groupBy)
 * against the current tenant's own `invoices`/`customers` tables - never
 * by loading whole collections into PHP/Vue to sum them client-side.
 *
 * "Revenue" = invoiced amount that isn't void: status issued or paid
 * (matches InvoicePaymentsController's own treatment of cancelled
 * invoices as excluded from money totals). Draft invoices aren't real
 * documents yet, so they're excluded too.
 */
class ReportsService
{
    public const PERIODS = ['this_month', 'last_month', 'last_3_months', 'last_6_months', 'this_year', 'custom'];

    private const REVENUE_STATUSES = [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID];

    public function resolvePeriod(string $period, ?string $from, ?string $to): array
    {
        $today = Carbon::today();

        [$start, $end] = match ($period) {
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_3_months' => [$today->copy()->subMonths(3)->addDay()->startOfDay(), $today->copy()->endOfDay()],
            'last_6_months' => [$today->copy()->subMonths(6)->addDay()->startOfDay(), $today->copy()->endOfDay()],
            'this_year'     => [$today->copy()->startOfYear(), $today->copy()->endOfDay()],
            'custom'        => [
                $from ? Carbon::parse($from)->startOfDay() : $today->copy()->startOfMonth(),
                $to ? Carbon::parse($to)->endOfDay() : $today->copy()->endOfDay(),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()], // this_month
        };

        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        $days = $start->diffInDays($end) + 1;
        $prevEnd   = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        return [
            'start'     => $start,
            'end'       => $end,
            'prevStart' => $prevStart,
            'prevEnd'   => $prevEnd,
        ];
    }

    public function summary(string $period, ?string $from, ?string $to): array
    {
        $range = $this->resolvePeriod($period, $from, $to);

        $current  = $this->kpis($range['start'], $range['end']);
        $previous = $this->kpis($range['prevStart'], $range['prevEnd']);

        return [
            'period' => [
                'start' => $range['start']->toDateString(),
                'end'   => $range['end']->toDateString(),
            ],
            'kpis' => [
                'revenue'          => $this->withChange($current['revenue'], $previous['revenue']),
                'collected'        => $this->withChange($current['collected'], $previous['collected']),
                'outstanding'      => $this->withChange($current['outstanding'], $previous['outstanding']),
                'invoices_count'   => $this->withChange($current['invoices_count'], $previous['invoices_count']),
            ],
            'chart'           => $this->revenueEvolution($range['start'], $range['end']),
            'invoice_status'  => $this->invoiceStatusBreakdown($range['start'], $range['end']),
            'payment_methods' => $this->paymentMethodsBreakdown($range['start'], $range['end']),
            'top_clients'     => $this->topClients($range['start'], $range['end']),
            'performance'     => $this->performance($range['start'], $range['end'], $current),
        ];
    }

    /**
     * Raw rows for the CSV export - one row per invoice in the period,
     * same status/revenue rules as everywhere else in this service.
     */
    public function exportRows(string $period, ?string $from, ?string $to): Collection
    {
        $range = $this->resolvePeriod($period, $from, $to);

        return Invoice::with('customer')
            ->whereIn('status', [...self::REVENUE_STATUSES, Invoice::STATUS_CANCELLED])
            ->whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()])
            ->orderBy('date')
            ->get();
    }

    // ── KPIs ─────────────────────────────────────────────────────

    private function kpis(Carbon $start, Carbon $end): array
    {
        $revenueRow = Invoice::whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(total), 0) as revenue, COUNT(*) as invoices_count')
            ->first();

        $collected = (float) Invoice::where('status', Invoice::STATUS_PAID)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start, $end])
                  ->orWhere(fn ($q2) => $q2->whereNull('paid_at')->whereBetween('updated_at', [$start, $end]));
            })
            ->sum('total');

        $outstanding = (float) Invoice::where('status', Invoice::STATUS_ISSUED)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->sum('total');

        return [
            'revenue'        => (float) $revenueRow->revenue,
            'invoices_count' => (int) $revenueRow->invoices_count,
            'collected'      => $collected,
            'outstanding'    => $outstanding,
        ];
    }

    private function withChange(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            $change = $current == 0 ? 0.0 : 100.0;
        } else {
            $change = (($current - $previous) / abs($previous)) * 100;
        }

        return [
            'value'       => $current,
            'change_pct'  => round($change, 1),
            'trend'       => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
        ];
    }

    // ── Revenue evolution chart ──────────────────────────────────

    private function revenueEvolution(Carbon $start, Carbon $end): array
    {
        $totalDays = $start->diffInDays($end) + 1;
        $bucket = $totalDays <= 31 ? 'day' : ($totalDays <= 200 ? 'week' : 'month');

        $format = match ($bucket) {
            'day'   => '%Y-%m-%d',
            'week'  => '%x-%v', // ISO year-week
            'month' => '%Y-%m',
        };

        $invoiced = Invoice::whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(date, '{$format}') as bucket, SUM(total) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $collected = Invoice::where('status', Invoice::STATUS_PAID)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start, $end])
                  ->orWhere(fn ($q2) => $q2->whereNull('paid_at')->whereBetween('updated_at', [$start, $end]));
            })
            ->selectRaw("DATE_FORMAT(COALESCE(paid_at, updated_at), '{$format}') as bucket, SUM(total) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $labels = $this->bucketLabels($start, $end, $bucket, $format);

        return [
            'granularity' => $bucket,
            'labels'      => array_values($labels),
            'invoiced'    => array_map(fn ($key) => round((float) ($invoiced[$key] ?? 0), 2), array_keys($labels)),
            'collected'   => array_map(fn ($key) => round((float) ($collected[$key] ?? 0), 2), array_keys($labels)),
        ];
    }

    /**
     * Ordered [bucketKey => displayLabel] map covering every bucket in
     * [start, end], even ones with zero activity - the chart's X axis
     * must stay continuous.
     */
    private function bucketLabels(Carbon $start, Carbon $end, string $bucket, string $format): array
    {
        $labels = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = match ($bucket) {
                'day'   => $cursor->format('Y-m-d'),
                'week'  => $cursor->format('o-W'),
                'month' => $cursor->format('Y-m'),
            };
            $display = match ($bucket) {
                'day'   => $cursor->translatedFormat('d/m'),
                'week'  => $cursor->translatedFormat('d/m'),
                'month' => $cursor->translatedFormat('M Y'),
            };
            $labels[$key] = $display;

            $cursor = match ($bucket) {
                'day'   => $cursor->addDay(),
                'week'  => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
            };
        }

        return $labels;
    }

    // ── Invoice status breakdown ─────────────────────────────────

    private function invoiceStatusBreakdown(Carbon $start, Carbon $end): array
    {
        $today = now()->toDateString();

        $rows = Invoice::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->selectRaw(
                "CASE
                    WHEN status = ? THEN 'paid'
                    WHEN status = ? THEN 'cancelled'
                    WHEN status = ? AND expiration_date IS NOT NULL AND expiration_date < ? THEN 'overdue'
                    ELSE 'pending'
                END as bucket, COUNT(*) as count, COALESCE(SUM(total), 0) as total",
                [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_ISSUED, $today]
            )
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $totalCount = (int) $rows->sum('count');

        $order = ['paid', 'pending', 'overdue', 'cancelled'];

        return array_map(function ($key) use ($rows, $totalCount) {
            $count = (int) ($rows[$key]->count ?? 0);

            return [
                'status'  => $key,
                'count'   => $count,
                'total'   => (float) ($rows[$key]->total ?? 0),
                'percent' => $totalCount > 0 ? round(($count / $totalCount) * 100, 1) : 0,
            ];
        }, $order);
    }

    // ── Payment methods breakdown ────────────────────────────────

    /**
     * Grouped by whatever `paid_via` values actually exist among paid
     * invoices in this period - never a hardcoded list, so a method this
     * tenant has never used (or one added later) is neither invented nor
     * silently missing.
     */
    private function paymentMethodsBreakdown(Carbon $start, Carbon $end): array
    {
        $rows = Invoice::where('status', Invoice::STATUS_PAID)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start, $end])
                  ->orWhere(fn ($q2) => $q2->whereNull('paid_at')->whereBetween('updated_at', [$start, $end]));
            })
            ->selectRaw('COALESCE(paid_via, \'\') as method, COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $totalAmount = (float) $rows->sum('total');

        return $rows->map(fn ($row) => [
            'method'  => $row->method !== '' ? $row->method : 'unspecified',
            'count'   => (int) $row->count,
            'total'   => (float) $row->total,
            'percent' => $totalAmount > 0 ? round(($row->total / $totalAmount) * 100, 1) : 0,
        ])->values()->all();
    }

    // ── Top clients ───────────────────────────────────────────────

    private function topClients(Carbon $start, Carbon $end): array
    {
        $rows = Invoice::whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id,
                COUNT(*) as invoices_count,
                COALESCE(SUM(total), 0) as invoiced,
                COALESCE(SUM(CASE WHEN status = ? THEN total ELSE 0 END), 0) as collected',
                [Invoice::STATUS_PAID]
            )
            ->groupBy('customer_id')
            ->orderByDesc('invoiced')
            ->limit(5)
            // `name` on Customer is a computed accessor, not a real column -
            // select the real columns it's built from instead.
            ->with('customer:id,first_name,last_name,company_name')
            ->get();

        return $rows->map(function ($row) {
            // Customer::name concatenates first/last name and can come back
            // as a blank/whitespace-only string for a company-only contact
            // (no personal name on file) - trim before falling back to the
            // company name so those clients don't show up as blank.
            $name = trim((string) $row->customer?->name);

            return [
                'client'          => $name !== '' ? $name : ($row->customer?->company_name ?: '—'),
                'invoices_count'  => (int) $row->invoices_count,
                'invoiced'        => (float) $row->invoiced,
                'collected'       => (float) $row->collected,
            ];
        })->values()->all();
    }

    // ── Performance ───────────────────────────────────────────────

    private function performance(Carbon $start, Carbon $end, array $current): array
    {
        $clientsInvoiced = (int) Invoice::whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('customer_id')
            ->distinct()
            ->count('customer_id');

        $avgInvoiceValue = $current['invoices_count'] > 0
            ? $current['revenue'] / $current['invoices_count']
            : 0.0;

        $collectionRate = $current['revenue'] > 0
            ? round(($current['collected'] / $current['revenue']) * 100, 1)
            : 0.0;

        return [
            'avg_invoice_value' => round($avgInvoiceValue, 2),
            'collection_rate'   => $collectionRate,
            'unpaid_amount'     => round($current['outstanding'], 2),
            'clients_invoiced'  => $clientsInvoiced,
        ];
    }
}
