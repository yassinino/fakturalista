<?php

namespace App\Http\Controllers;

use App\Services\ReportsService;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(private ReportsService $reports)
    {
    }

    // GET /api/reports/summary?period=this_month|last_month|last_3_months|last_6_months|this_year|custom&from=&to=
    public function summary(Request $request): JsonResponse
    {
        $validated = $this->validatePeriod($request);

        $data = $this->reports->summary($validated['period'], $validated['from'] ?? null, $validated['to'] ?? null);
        $data['currency'] = app(TenantContextService::class)->currency();

        return response()->json($data);
    }

    // GET /api/reports/export?period=...&from=&to=
    public function export(Request $request): StreamedResponse
    {
        $validated = $this->validatePeriod($request);
        $currency  = app(TenantContextService::class)->currency();

        $rows = $this->reports->exportRows($validated['period'], $validated['from'] ?? null, $validated['to'] ?? null);

        $filename = 'fakturalista-report-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows, $currency) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date', 'Due date', 'Customer', 'Status', 'Total', 'Currency', 'Paid at', 'Paid via']);

            foreach ($rows as $invoice) {
                fputcsv($out, [
                    $invoice->reference,
                    $invoice->date,
                    $invoice->expiration_date,
                    $invoice->customer?->name ?: $invoice->customer?->company_name,
                    $invoice->status,
                    number_format((float) $invoice->total, 2, '.', ''),
                    $currency,
                    $invoice->paid_at?->toDateString(),
                    $invoice->paid_via,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function validatePeriod(Request $request): array
    {
        return Validator::make($request->all(), [
            'period' => 'nullable|string|in:' . implode(',', ReportsService::PERIODS),
            'from'   => 'nullable|date',
            'to'     => 'nullable|date',
        ])->validate() + ['period' => $request->input('period', 'this_month')];
    }
}
