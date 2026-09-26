<?php

namespace App\Services\Export;

use App\Models\Invoice;
use App\Services\ReportsService;
use App\Services\TenantContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The richer export the Reports page needs on top of what
 * TableExportService already gives every other page: a branded,
 * multi-section PDF and a multi-sheet workbook (Summary/Invoices/
 * Payments/Clients), both scoped to the same period as the on-screen
 * report (ReportsService::resolvePeriod()/summary() - never re-derived
 * here, so the export can never disagree with what's on screen).
 */
class ReportExportService
{
    public function __construct(
        private ReportsService $reports,
        private TableExportService $tables,
    ) {
    }

    public function pdf(string $period, ?string $from, ?string $to): string
    {
        $range   = $this->reports->resolvePeriod($period, $from, $to);
        $summary = $this->reports->summary($period, $from, $to);
        [$companyName, $logoSrc] = $this->tables->branding();

        $context  = app(TenantContextService::class);
        $locale   = $context->locale();
        $currency = $context->currency();
        $formatter = app(\App\Services\CurrencyFormatter::class);
        $formatMoney = fn (float $v) => $formatter->format($v, $currency, $locale);

        $clients = $this->reports->clientsBreakdown($range['start'], $range['end']);

        return Pdf::loadView('pdf.exports.report', [
            'companyName' => $companyName,
            'logoSrc'     => $logoSrc,
            'periodLabel' => $this->periodLabel($range['start'], $range['end'], $locale),
            'summary'     => $summary,
            'clients'     => $clients,
            'formatMoney' => $formatMoney,
            'generatedAt' => now(),
            'labels'      => $this->reportLabels($locale) + $this->tables->pdfLabels($locale),
            'isRtl'       => $locale === 'ar',
        ])->setPaper('a4', 'portrait')->output();
    }

    public function excel(string $period, ?string $from, ?string $to): StreamedResponse
    {
        $range   = $this->reports->resolvePeriod($period, $from, $to);
        $summary = $this->reports->summary($period, $from, $to);

        $context  = app(TenantContextService::class);
        $locale   = $context->locale();
        $currency = $context->currency();
        $formatter = app(\App\Services\CurrencyFormatter::class);
        $formatMoney = fn (float $v) => $formatter->format($v, $currency, $locale);

        $labels = $this->reportLabels($locale);

        $spreadsheet = new Spreadsheet();

        // ── Summary sheet ──────────────────────────────────────
        $this->tables->addSheet($spreadsheet, $labels['summary'], [$labels['metric'], $labels['value']], [
            [$labels['revenue'], $formatMoney($summary['kpis']['revenue']['value'])],
            [$labels['collected'], $formatMoney($summary['kpis']['collected']['value'])],
            [$labels['outstanding'], $formatMoney($summary['kpis']['outstanding']['value'])],
            [$labels['invoices_count'], $summary['kpis']['invoices_count']['value']],
            [$labels['avg_invoice_value'], $formatMoney($summary['performance']['avg_invoice_value'])],
            [$labels['collection_rate'], $summary['performance']['collection_rate'] . '%'],
            [$labels['unpaid_amount'], $formatMoney($summary['performance']['unpaid_amount'])],
            [$labels['clients_invoiced'], $summary['performance']['clients_invoiced']],
        ], isFirst: true);

        // ── Invoices sheet (every issued/paid/cancelled invoice in period) ──
        $invoiceRows = $this->reports->exportRows($period, $from, $to);
        $this->tables->addSheet($spreadsheet, $labels['invoices'], [
            $labels['col_reference'], $labels['col_date'], $labels['col_customer'],
            $labels['col_status'], $labels['col_total'], $labels['col_paid_at'], $labels['col_paid_via'],
        ], $invoiceRows->map(fn (Invoice $inv) => [
            $inv->reference,
            $inv->date,
            $this->customerName($inv->customer),
            $inv->status,
            $formatMoney((float) $inv->total),
            $inv->paid_at?->toDateString() ?: '',
            $inv->paid_via ?: '',
        ])->all());

        // ── Payments sheet (the paid subset) ────────────────────
        $paidRows = $invoiceRows->filter(fn (Invoice $inv) => $inv->status === Invoice::STATUS_PAID);
        $this->tables->addSheet($spreadsheet, $labels['payments'], [
            $labels['col_reference'], $labels['col_paid_at'], $labels['col_customer'],
            $labels['col_paid_via'], $labels['col_total'],
        ], $paidRows->map(fn (Invoice $inv) => [
            $inv->reference,
            $inv->paid_at?->toDateString() ?: $inv->date,
            $this->customerName($inv->customer),
            $inv->paid_via ?: '',
            $formatMoney((float) $inv->total),
        ])->values()->all());

        // ── Clients sheet (full breakdown, not just the on-screen top 5) ──
        $clients = $this->reports->clientsBreakdown($range['start'], $range['end']);
        $this->tables->addSheet($spreadsheet, $labels['clients'], [
            $labels['col_customer'], $labels['col_invoices_count'], $labels['col_invoiced'], $labels['col_collected'],
        ], array_map(fn ($c) => [
            $c['client'], $c['invoices_count'], $formatMoney($c['invoiced']), $formatMoney($c['collected']),
        ], $clients));

        $filename = 'business-report-' . $range['start']->toDateString() . '-to-' . $range['end']->toDateString() . '.xlsx';

        return $this->tables->streamSpreadsheet($spreadsheet, $filename);
    }

    private function customerName($customer): string
    {
        if (!$customer) {
            return '';
        }
        $name = trim((string) $customer->name);

        return $name !== '' ? $name : ($customer->company_name ?: '');
    }

    private function periodLabel(Carbon $start, Carbon $end, string $locale): string
    {
        \Illuminate\Support\Facades\App::setLocale($locale);

        return $start->translatedFormat('d/m/Y') . ' — ' . $end->translatedFormat('d/m/Y');
    }

    private function reportLabels(string $locale): array
    {
        return match ($locale) {
            'ar' => [
                'summary' => 'الملخص', 'invoices' => 'الفواتير', 'payments' => 'المدفوعات', 'clients' => 'العملاء',
                'metric' => 'المؤشر', 'value' => 'القيمة',
                'revenue' => 'الإيرادات', 'collected' => 'المبلغ المحصَّل', 'outstanding' => 'المبلغ المستحق',
                'invoices_count' => 'عدد الفواتير', 'avg_invoice_value' => 'متوسط قيمة الفاتورة',
                'collection_rate' => 'معدّل التحصيل', 'unpaid_amount' => 'إجمالي المبلغ غير المدفوع',
                'clients_invoiced' => 'العملاء المفوترون',
                'col_reference' => 'المرجع', 'col_date' => 'التاريخ', 'col_customer' => 'العميل',
                'col_status' => 'الحالة', 'col_total' => 'الإجمالي', 'col_paid_at' => 'تاريخ الدفع',
                'col_paid_via' => 'طريقة الدفع', 'col_invoices_count' => 'عدد الفواتير',
                'col_invoiced' => 'المفوتر', 'col_collected' => 'المحصَّل',
                'report_title' => 'تقرير الأعمال', 'period' => 'الفترة المشمولة بالتقرير',
                'status_breakdown' => 'توزيع حالة الفواتير', 'method_breakdown' => 'توزيع طرق الدفع',
                'top_clients' => 'أفضل العملاء', 'revenue_evolution' => 'ملخص تطور الإيرادات',
                'count' => 'العدد', 'percent' => 'النسبة', 'amount' => 'المبلغ', 'period_bucket' => 'الفترة',
            ],
            'en' => [
                'summary' => 'Summary', 'invoices' => 'Invoices', 'payments' => 'Payments', 'clients' => 'Clients',
                'metric' => 'Metric', 'value' => 'Value',
                'revenue' => 'Revenue', 'collected' => 'Amount collected', 'outstanding' => 'Outstanding amount',
                'invoices_count' => 'Number of invoices', 'avg_invoice_value' => 'Average invoice value',
                'collection_rate' => 'Collection rate', 'unpaid_amount' => 'Total unpaid amount',
                'clients_invoiced' => 'Clients invoiced',
                'col_reference' => 'Reference', 'col_date' => 'Date', 'col_customer' => 'Client',
                'col_status' => 'Status', 'col_total' => 'Total', 'col_paid_at' => 'Paid at',
                'col_paid_via' => 'Paid via', 'col_invoices_count' => 'Invoices',
                'col_invoiced' => 'Invoiced', 'col_collected' => 'Collected',
                'report_title' => 'Business report', 'period' => 'Reporting period',
                'status_breakdown' => 'Invoice status breakdown', 'method_breakdown' => 'Payment method breakdown',
                'top_clients' => 'Top clients', 'revenue_evolution' => 'Revenue evolution summary',
                'count' => 'Count', 'percent' => 'Percent', 'amount' => 'Amount', 'period_bucket' => 'Period',
            ],
            default => [
                'summary' => 'Résumé', 'invoices' => 'Factures', 'payments' => 'Paiements', 'clients' => 'Clients',
                'metric' => 'Indicateur', 'value' => 'Valeur',
                'revenue' => 'Revenus', 'collected' => 'Montant encaissé', 'outstanding' => 'Montant en attente',
                'invoices_count' => 'Nombre de factures', 'avg_invoice_value' => 'Valeur moyenne des factures',
                'collection_rate' => "Taux d'encaissement", 'unpaid_amount' => 'Montant total impayé',
                'clients_invoiced' => 'Clients facturés',
                'col_reference' => 'Référence', 'col_date' => 'Date', 'col_customer' => 'Client',
                'col_status' => 'Statut', 'col_total' => 'Total', 'col_paid_at' => 'Payée le',
                'col_paid_via' => 'Moyen de paiement', 'col_invoices_count' => 'Factures',
                'col_invoiced' => 'Facturé', 'col_collected' => 'Encaissé',
                'report_title' => 'Rapport commercial', 'period' => 'Période du rapport',
                'status_breakdown' => 'Répartition des statuts de factures', 'method_breakdown' => 'Répartition des modes de paiement',
                'top_clients' => 'Meilleurs clients', 'revenue_evolution' => "Résumé de l'évolution du revenu",
                'count' => 'Nombre', 'percent' => 'Pourcentage', 'amount' => 'Montant', 'period_bucket' => 'Période',
            ],
        };
    }
}
