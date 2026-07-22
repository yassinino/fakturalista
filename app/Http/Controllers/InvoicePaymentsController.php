<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoicePaymentsController extends Controller
{
    // GET /api/payments — paginated list with filters & sorting
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $query = Invoice::with('customer')
            ->whereIn('status', [
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PAID,
                Invoice::STATUS_CANCELLED,
            ]);

        // Full-text search on reference and customer name
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($cq) =>
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%")
                  );
            });
        }

        // Payment-status filter (mapped from invoice status + overdue logic)
        if ($status = $request->input('status')) {
            match ($status) {
                'paid'      => $query->where('status', Invoice::STATUS_PAID),
                'pending'   => $query->where('status', Invoice::STATUS_ISSUED)
                                     ->where(fn($q) =>
                                         $q->where('expiration_date', '>=', $today)
                                           ->orWhereNull('expiration_date')
                                     ),
                'overdue'   => $query->where('status', Invoice::STATUS_ISSUED)
                                     ->where('expiration_date', '<', $today),
                'cancelled' => $query->where('status', Invoice::STATUS_CANCELLED),
                default     => null,
            };
        }

        // Client filter
        if ($clientUuid = $request->input('client_id')) {
            $query->whereHas('customer', fn($q) => $q->where('uuid', $clientUuid));
        }

        // Date-range filter (on invoice date)
        if ($dateFrom = $request->input('date_from')) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('date', '<=', $dateTo);
        }

        // Sorting
        match ($request->input('sort', 'newest')) {
            'oldest' => $query->orderBy('date')->orderBy('created_at'),
            'amount' => $query->orderByDesc('total'),
            'status' => $query->orderBy('status'),
            default  => $query->orderByDesc('date')->orderByDesc('created_at'),
        };

        $paginator = $query->paginate((int) $request->input('per_page', 15));

        $items = $paginator->getCollection()->map(fn($inv) => $this->toRecord($inv, $today));

        return response()->json([
            'payments' => $items,
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // GET /api/payments/summary — 4 KPI card values
    public function summary(): JsonResponse
    {
        $today = now()->toDateString();
        $start = now()->startOfMonth()->startOfDay();
        $end   = now()->endOfMonth()->endOfDay();

        $totalReceived = (float) Invoice::where('status', Invoice::STATUS_PAID)->sum('total');

        $pending = (float) Invoice::where('status', Invoice::STATUS_ISSUED)
            ->where(fn($q) => $q->where('expiration_date', '>=', $today)->orWhereNull('expiration_date'))
            ->sum('total');

        $overdue = (float) Invoice::where('status', Invoice::STATUS_ISSUED)
            ->where('expiration_date', '<', $today)
            ->sum('total');

        // Paid this month — prefer paid_at, fall back to updated_at for legacy records
        $thisMonth = (float) Invoice::where('status', Invoice::STATUS_PAID)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start, $end])
                  ->orWhere(fn($q2) =>
                      $q2->whereNull('paid_at')->whereBetween('updated_at', [$start, $end])
                  );
            })
            ->sum('total');

        return response()->json(compact('totalReceived', 'pending', 'overdue', 'thisMonth'));
    }

    // GET /api/payments/clients — clients who have invoices (for filter dropdown)
    public function clients(): JsonResponse
    {
        $customerIds = Invoice::whereIn('status', [
            Invoice::STATUS_ISSUED,
            Invoice::STATUS_PAID,
            Invoice::STATUS_CANCELLED,
        ])->distinct()->pluck('customer_id');

        $clients = Customer::whereIn('id', $customerIds)
            ->orderBy('name')
            ->get(['uuid', 'name', 'company_name'])
            ->map(fn($c) => [
                'uuid'  => $c->uuid,
                'label' => $c->name ?: ($c->company_name ?: $c->uuid),
            ]);

        return response()->json(['clients' => $clients]);
    }

    // GET /api/payments/payable — issued invoices available to be marked paid
    public function payable(): JsonResponse
    {
        $today = now()->toDateString();

        $invoices = Invoice::with('customer')
            ->where('status', Invoice::STATUS_ISSUED)
            ->orderByDesc('date')
            ->get(['id', 'uuid', 'reference', 'customer_id', 'total', 'date', 'expiration_date'])
            ->map(fn($inv) => [
                'uuid'      => $inv->uuid,
                'reference' => $inv->reference,
                'customer'  => $inv->customer?->name ?? '—',
                'total'     => (float) ($inv->total ?? 0),
                'date'      => $inv->date,
                'due_date'  => $inv->expiration_date,
                'overdue'   => !empty($inv->expiration_date) && $inv->expiration_date < $today,
            ]);

        return response()->json(['invoices' => $invoices]);
    }

    // POST /api/payments/record — manually mark an issued invoice as paid
    public function record(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_uuid'   => 'required|string',
            'payment_method' => 'nullable|string|max:50',
            'payment_date'   => 'nullable|date',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $invoice = Invoice::where('uuid', $request->invoice_uuid)->firstOrFail();

        if (!$invoice->isIssued()) {
            return response()->json([
                'message' => 'Only issued invoices can be recorded as paid.',
            ], 422);
        }

        $invoice->status   = Invoice::STATUS_PAID;
        $invoice->paid_at  = $request->payment_date ? Carbon::parse($request->payment_date) : now();
        $invoice->paid_via = $request->payment_method ?: null;

        if ($request->filled('notes')) {
            $invoice->note = $invoice->note
                ? $invoice->note . "\n" . $request->notes
                : $request->notes;
        }

        $invoice->save();

        $invoice->logHistory(InvoiceHistory::ACTION_PAID, [
            'via'    => $invoice->paid_via,
            'date'   => $invoice->paid_at->toDateString(),
            'source' => 'manual',
        ]);

        Log::info('Payment recorded', [
            'invoice_uuid' => $invoice->uuid,
            'reference'    => $invoice->reference,
            'amount'       => $invoice->total,
            'method'       => $invoice->paid_via,
        ]);

        $today = now()->toDateString();

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => $this->toRecord($invoice->fresh(['customer']), $today),
        ]);
    }

    // PUT /api/payments/{invoice} — edit payment metadata (method / date / notes)
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'payment_date'   => 'nullable|date',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $before = ['paid_via' => $invoice->paid_via, 'paid_at' => $invoice->paid_at?->toDateString()];

        if ($request->has('payment_method')) {
            $invoice->paid_via = $request->payment_method ?: null;
        }
        if ($request->filled('payment_date')) {
            $invoice->paid_at = Carbon::parse($request->payment_date);
        }
        if ($request->has('notes')) {
            $invoice->note = $request->notes;
        }

        $invoice->save();

        Log::info('Payment updated', [
            'invoice_uuid' => $invoice->uuid,
            'before'       => $before,
            'after'        => ['paid_via' => $invoice->paid_via, 'paid_at' => $invoice->paid_at?->toDateString()],
        ]);

        $today = now()->toDateString();

        return response()->json([
            'message' => 'Payment updated.',
            'payment' => $this->toRecord($invoice->fresh(['customer']), $today),
        ]);
    }

    // GET /api/payments/{invoice} — single payment detail
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->loadMissing('customer');
        $today = now()->toDateString();

        return response()->json(['payment' => $this->toRecord($invoice, $today)]);
    }

    // ── Private helpers ────────────────────────────────────────

    private function toRecord(Invoice $invoice, string $today): array
    {
        return [
            'payment_number'        => 'PAY-' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT),
            'invoice_uuid'          => $invoice->uuid,
            'invoice_reference'     => $invoice->reference,
            'client_name'           => $invoice->customer?->name ?? '—',
            'client_email'          => $invoice->customer?->email ?? '',
            'client_uuid'           => $invoice->customer?->uuid ?? '',
            'amount'                => (float) ($invoice->total ?? 0),
            'payment_method'        => $invoice->paid_via,
            'status'                => $this->deriveStatus($invoice, $today),
            'payment_date'          => $invoice->paid_at?->toDateString(),
            'invoice_date'          => $invoice->date,
            'due_date'              => $invoice->expiration_date,
            'transaction_reference' => $invoice->stripe_session_id,
            'notes'                 => $invoice->note,
            'paid_at'               => $invoice->paid_at?->toISOString(),
        ];
    }

    private function deriveStatus(Invoice $invoice, string $today): string
    {
        return match ($invoice->status) {
            Invoice::STATUS_PAID      => 'paid',
            Invoice::STATUS_CANCELLED => 'cancelled',
            Invoice::STATUS_ISSUED    =>
                (!empty($invoice->expiration_date) && $invoice->expiration_date < $today)
                    ? 'overdue'
                    : 'pending',
            default => $invoice->status,
        };
    }
}
