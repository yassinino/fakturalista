<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function counts()
    {
        return response()->json([
            'invoices' => Invoice::count(),
            'customers' => Customer::count(),
            'items' => Item::count(),
            'total_earnings' => Invoice::sum(DB::raw('COALESCE(total, 0)')),
        ]);
    }

    public function cashOverview()
    {
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        // Issued invoices whose due date falls in the current calendar month
        $expected = Invoice::where('status', Invoice::STATUS_ISSUED)
            ->whereBetween('expiration_date', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(SUM(total), 0) as amount, COUNT(*) as count')
            ->first();

        // Issued invoices whose due date has already passed
        $overdue = Invoice::where('status', Invoice::STATUS_ISSUED)
            ->where('expiration_date', '<', $today)
            ->selectRaw('COALESCE(SUM(total), 0) as amount, COUNT(*) as count')
            ->first();

        // Invoices marked paid this calendar month (via audit history for accuracy)
        $received = DB::table('invoice_history as h')
            ->join('invoices as i', 'i.id', '=', 'h.invoice_id')
            ->where('h.action', InvoiceHistory::ACTION_PAID)
            ->whereBetween('h.created_at', [
                now()->startOfMonth()->startOfDay(),
                now()->endOfMonth()->endOfDay(),
            ])
            ->whereNull('i.deleted_at')
            ->selectRaw('COALESCE(SUM(i.total), 0) as amount, COUNT(*) as count')
            ->first();

        // Top 5 most overdue invoices for the action list (oldest due date first)
        $urgent = Invoice::where('status', Invoice::STATUS_ISSUED)
            ->where('expiration_date', '<', $today)
            ->with('customer:id,name')
            ->select('uuid', 'reference', 'total', 'expiration_date', 'customer_id')
            ->orderBy('expiration_date', 'asc')
            ->limit(5)
            ->get()
            ->map(fn ($inv) => [
                'uuid'        => $inv->uuid,
                'reference'   => $inv->reference,
                'total'       => (float) ($inv->total ?? 0),
                'customer'    => $inv->customer?->name ?? '-',
                'days_overdue'=> (int) now()->diffInDays($inv->expiration_date),
            ]);

        return response()->json([
            'expected' => ['amount' => (float) $expected->amount, 'count' => (int) $expected->count],
            'overdue'  => ['amount' => (float) $overdue->amount,  'count' => (int) $overdue->count],
            'received' => ['amount' => (float) $received->amount, 'count' => (int) $received->count],
            'urgent_overdue' => $urgent,
        ]);
    }
}
