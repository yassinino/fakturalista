<?php

namespace App\Http\Controllers;

use App\Services\Export\ReportExportService;
use App\Services\Export\TableExportService;
use App\Services\ReportsService;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private ReportsService $reports,
        private TableExportService $tables,
        private ReportExportService $reportExporter,
    ) {
    }

    // GET /api/reports/summary?period=this_month|last_month|last_3_months|last_6_months|this_year|custom&from=&to=
    public function summary(Request $request): JsonResponse
    {
        $validated = $this->validatePeriod($request);

        $data = $this->reports->summary($validated['period'], $validated['from'] ?? null, $validated['to'] ?? null);
        $data['currency'] = app(TenantContextService::class)->currency();

        return response()->json($data);
    }

    // GET /api/reports/export?period=...&from=&to=&format=pdf|xlsx|csv
    public function export(Request $request): StreamedResponse|Response
    {
        $validated = $this->validatePeriod($request);
        $format    = $validated['format'];
        $period    = $validated['period'];
        $from      = $validated['from'] ?? null;
        $to        = $validated['to'] ?? null;

        if ($format === 'pdf') {
            $pdf = $this->reportExporter->pdf($period, $from, $to);

            return response($pdf, 200, ['Content-Type' => 'application/pdf']);
        }

        if ($format === 'xlsx') {
            return $this->reportExporter->excel($period, $from, $to);
        }

        // CSV: the detailed underlying invoice rows for the period.
        $currency = app(TenantContextService::class)->currency();
        $range    = $this->reports->resolvePeriod($period, $from, $to);
        $rows     = $this->reports->exportRows($period, $from, $to);

        $headings = ['Reference', 'Date', 'Due date', 'Customer', 'Status', 'Total', 'Currency', 'Paid at', 'Paid via'];
        $csvRows  = $rows->map(fn ($invoice) => [
            $invoice->reference,
            $invoice->date,
            $invoice->expiration_date,
            $invoice->customer?->name ?: $invoice->customer?->company_name,
            $invoice->status,
            number_format((float) $invoice->total, 2, '.', ''),
            $currency,
            $invoice->paid_at?->toDateString(),
            $invoice->paid_via,
        ])->all();

        $filename = 'business-report-' . $range['start']->toDateString() . '-to-' . $range['end']->toDateString() . '.csv';

        return $this->tables->csv($filename, $headings, $csvRows);
    }

    private function validatePeriod(Request $request): array
    {
        return Validator::make($request->all(), [
            'period' => 'nullable|string|in:' . implode(',', ReportsService::PERIODS),
            'from'   => 'nullable|date',
            'to'     => 'nullable|date',
            'format' => 'nullable|string|in:pdf,xlsx,csv',
        ])->validate() + [
            'period' => $request->input('period', 'this_month'),
            'format' => $request->input('format', 'csv'),
        ];
    }
}
