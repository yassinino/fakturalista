<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\CurrencyFormatter;
use App\Services\Export\TableExportService;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export endpoints for Invoices/Quotes/Payments/Clients. Each method
 * rebuilds the SAME filter query its page's own index() already uses
 * (so "export respects current filters" is exact, not approximated),
 * then hands headings+rows to TableExportService for the actual file.
 *
 * Never trusts a tenant/company id from the request - CompanyProfile is
 * always CompanyProfile::first() on the current tenant DB connection
 * (already selected by stancl/tenancy before this controller runs), the
 * same as every other tenant controller in this app.
 */
class ExportController extends Controller
{
    private const FORMATS = ['pdf', 'xlsx', 'csv'];

    public function __construct(private TableExportService $exporter)
    {
    }

    // ── Invoices ─────────────────────────────────────────────────

    public function invoices(Request $request)
    {
        $format = $this->validateFormat($request);
        [$locale, $currency, $formatMoney] = $this->context();

        $query = Invoice::with('customer');
        $this->applyInvoiceFilters($query, $request);

        $labels = $this->invoiceLabels($locale);
        $headings = array_values($labels);
        $rows = $query->orderByDesc('date')->get()->map(function (Invoice $invoice) use ($formatMoney, $currency) {
            $paid = $invoice->status === Invoice::STATUS_PAID ? (float) $invoice->total : 0.0;
            $remaining = in_array($invoice->status, [Invoice::STATUS_ISSUED], true) ? (float) $invoice->total : 0.0;

            return [
                $invoice->reference,
                $invoice->date,
                $this->customerName($invoice->customer),
                $invoice->status,
                $formatMoney((float) $invoice->sub_total),
                $formatMoney((float) $invoice->vta),
                $formatMoney((float) $invoice->total),
                $formatMoney($paid),
                $formatMoney($remaining),
                $currency,
            ];
        })->all();

        return $this->respond($format, 'invoices', $labels['ref'] ?? 'Invoices', $headings, $rows);
    }

    private function applyInvoiceFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('date', '<=', $dateTo);
        }
    }

    // ── Quotes ───────────────────────────────────────────────────

    public function quotes(Request $request)
    {
        $format = $this->validateFormat($request);
        [$locale, $currency, $formatMoney] = $this->context();

        $query = Quote::with('customer');
        $this->applyQuoteFilters($query, $request);

        $labels = $this->quoteLabels($locale);
        $headings = array_values($labels);
        $rows = $query->orderByDesc('date')->get()->map(fn (Quote $quote) => [
            $quote->reference,
            $quote->date,
            $this->customerName($quote->customer),
            $quote->status,
            $formatMoney((float) $quote->sub_total),
            $formatMoney((float) $quote->vta),
            $formatMoney((float) $quote->total),
            $currency,
        ])->all();

        return $this->respond($format, 'quotes', $labels['ref'] ?? 'Quotes', $headings, $rows);
    }

    // Mirrors DataTableShell's own client-side filter semantics (quotes/index.vue
    // runs it purely client-side - see DataTableShell.vue's filteredRows), so an
    // export matches exactly what's on screen instead of returning everything.
    private function applyQuoteFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('date', '<=', $dateTo);
        }
    }

    // ── Payments (derived from invoices - matches InvoicePaymentsController) ──

    public function payments(Request $request)
    {
        $format = $this->validateFormat($request);
        [$locale, $currency, $formatMoney] = $this->context();
        $today = now()->toDateString();

        $query = Invoice::with('customer')->whereIn('status', [
            Invoice::STATUS_ISSUED, Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED,
        ]);
        $this->applyPaymentFilters($query, $request, $today);

        $labels = $this->paymentLabels($locale);
        $headings = array_values($labels);
        $rows = $query->orderByDesc('date')->get()->map(function (Invoice $invoice) use ($formatMoney, $currency, $today) {
            return [
                'PAY-' . str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT),
                $invoice->paid_at?->toDateString() ?: $invoice->date,
                $this->customerName($invoice->customer),
                $invoice->reference,
                $invoice->paid_via ?: '',
                $formatMoney((float) $invoice->total),
                $currency,
                $this->derivePaymentStatus($invoice, $today),
            ];
        })->all();

        return $this->respond($format, 'payments', $labels['ref'] ?? 'Payments', $headings, $rows);
    }

    // Exact same filters/mapping as InvoicePaymentsController::index()/deriveStatus().
    private function applyPaymentFilters($query, Request $request, string $today): void
    {
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->input('status')) {
            match ($status) {
                'paid'      => $query->where('status', Invoice::STATUS_PAID),
                'pending'   => $query->where('status', Invoice::STATUS_ISSUED)
                                     ->where(fn ($q) => $q->where('expiration_date', '>=', $today)->orWhereNull('expiration_date')),
                'overdue'   => $query->where('status', Invoice::STATUS_ISSUED)->where('expiration_date', '<', $today),
                'cancelled' => $query->where('status', Invoice::STATUS_CANCELLED),
                default     => null,
            };
        }
        if ($clientUuid = $request->input('client_id')) {
            $query->whereHas('customer', fn ($q) => $q->where('uuid', $clientUuid));
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('date', '<=', $dateTo);
        }
    }

    private function derivePaymentStatus(Invoice $invoice, string $today): string
    {
        return match ($invoice->status) {
            Invoice::STATUS_PAID      => 'paid',
            Invoice::STATUS_CANCELLED => 'cancelled',
            Invoice::STATUS_ISSUED    => (!empty($invoice->expiration_date) && $invoice->expiration_date < $today) ? 'overdue' : 'pending',
            default => $invoice->status,
        };
    }

    // ── Clients ──────────────────────────────────────────────────

    public function clients(Request $request)
    {
        $format = $this->validateFormat($request);
        [$locale, $currency, $formatMoney] = $this->context();

        $query = Customer::query();
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('created_at')->get();

        // One aggregate query for every listed customer's invoice totals -
        // avoids N+1 (a per-customer sum() in the map() below).
        $stats = Invoice::whereIn('customer_id', $customers->pluck('id'))
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID])
            ->selectRaw(
                'customer_id, COUNT(*) as invoices_count, COALESCE(SUM(total), 0) as total_invoiced,
                 COALESCE(SUM(CASE WHEN status = ? THEN total ELSE 0 END), 0) as total_paid',
                [Invoice::STATUS_PAID]
            )
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $labels = $this->clientLabels($locale);
        $headings = array_values($labels);
        $rows = $customers->map(function (Customer $customer) use ($stats, $formatMoney) {
            $s = $stats->get($customer->id);
            $invoiced = (float) ($s->total_invoiced ?? 0);
            $paid = (float) ($s->total_paid ?? 0);

            return [
                $this->customerName($customer),
                $customer->company_name ?: '',
                $customer->email ?: '',
                $customer->phone ?: '',
                $customer->tax_id ?: ($customer->vat_number ?: ($customer->ice ?: ($customer->if_number ?: ''))),
                (int) ($s->invoices_count ?? 0),
                $formatMoney($invoiced),
                $formatMoney($paid),
                $formatMoney($invoiced - $paid),
            ];
        })->all();

        return $this->respond($format, 'clients', $labels['ref'] ?? 'Clients', $headings, $rows);
    }

    // ── Shared helpers ───────────────────────────────────────────

    private function validateFormat(Request $request): string
    {
        return Validator::make($request->all(), [
            'format' => 'required|string|in:' . implode(',', self::FORMATS),
        ])->validate()['format'];
    }

    /**
     * @return array{0: string, 1: string, 2: callable(float):string}
     */
    private function context(): array
    {
        $context = app(TenantContextService::class);
        $locale = $context->locale();
        $currency = $context->currency();
        $formatter = app(CurrencyFormatter::class);

        return [$locale, $currency, fn (float $v) => $formatter->format($v, $currency, $locale)];
    }

    private function customerName($customer): string
    {
        if (!$customer) {
            return '';
        }
        $name = trim((string) $customer->name);

        return $name !== '' ? $name : ($customer->company_name ?: '');
    }

    /**
     * @param  string  $prefix  filename prefix, e.g. "invoices"
     * @param  string  $title   PDF title / xlsx sheet title
     */
    private function respond(string $format, string $prefix, string $title, array $headings, array $rows): Response|StreamedResponse
    {
        $filename = $prefix . '-' . now()->format('Y-m');

        return match ($format) {
            'csv'  => $this->exporter->csv("{$filename}.csv", $headings, $rows),
            'xlsx' => $this->exporter->excel("{$filename}.xlsx", $title, $headings, $rows),
            'pdf'  => response($this->exporter->listPdf($title, null, $headings, $rows), 200, ['Content-Type' => 'application/pdf']),
        };
    }

    // ── Locale-aware column labels (same "inline map, no lang file" pattern
    // as Pdf\TemplateRendererService::resolveStatus() - these are business-
    // document text, not admin-UI text, so they follow the tenant's own
    // document locale, never the logged-in staff member's UI locale.) ────

    private function invoiceLabels(string $locale): array
    {
        return match ($locale) {
            'ar' => ['ref' => 'الفواتير', 'number' => 'رقم الفاتورة', 'date' => 'التاريخ', 'customer' => 'العميل', 'status' => 'الحالة', 'sub_total' => 'المجموع الفرعي', 'tax' => 'الضريبة', 'total' => 'الإجمالي', 'paid' => 'المدفوع', 'remaining' => 'المتبقي', 'currency' => 'العملة'],
            'en' => ['ref' => 'Invoices', 'number' => 'Invoice number', 'date' => 'Date', 'customer' => 'Client', 'status' => 'Status', 'sub_total' => 'Subtotal', 'tax' => 'Tax', 'total' => 'Total', 'paid' => 'Amount paid', 'remaining' => 'Amount remaining', 'currency' => 'Currency'],
            default => ['ref' => 'Factures', 'number' => 'N° de facture', 'date' => 'Date', 'customer' => 'Client', 'status' => 'Statut', 'sub_total' => 'Sous-total', 'tax' => 'Taxe', 'total' => 'Total', 'paid' => 'Montant payé', 'remaining' => 'Montant restant', 'currency' => 'Devise'],
        };
    }

    private function quoteLabels(string $locale): array
    {
        return match ($locale) {
            'ar' => ['ref' => 'عروض الأسعار', 'number' => 'رقم عرض السعر', 'date' => 'التاريخ', 'customer' => 'العميل', 'status' => 'الحالة', 'sub_total' => 'المجموع الفرعي', 'tax' => 'الضريبة', 'total' => 'الإجمالي', 'currency' => 'العملة'],
            'en' => ['ref' => 'Quotes', 'number' => 'Quote number', 'date' => 'Date', 'customer' => 'Client', 'status' => 'Status', 'sub_total' => 'Subtotal', 'tax' => 'Tax', 'total' => 'Total', 'currency' => 'Currency'],
            default => ['ref' => 'Devis', 'number' => 'N° de devis', 'date' => 'Date', 'customer' => 'Client', 'status' => 'Statut', 'sub_total' => 'Sous-total', 'tax' => 'Taxe', 'total' => 'Total', 'currency' => 'Devise'],
        };
    }

    private function paymentLabels(string $locale): array
    {
        return match ($locale) {
            'ar' => ['ref' => 'المدفوعات', 'number' => 'رقم الدفعة', 'date' => 'التاريخ', 'customer' => 'العميل', 'invoice' => 'الفاتورة المرتبطة', 'method' => 'طريقة الدفع', 'amount' => 'المبلغ', 'currency' => 'العملة', 'status' => 'الحالة'],
            'en' => ['ref' => 'Payments', 'number' => 'Payment reference', 'date' => 'Date', 'customer' => 'Client', 'invoice' => 'Related invoice', 'method' => 'Payment method', 'amount' => 'Amount', 'currency' => 'Currency', 'status' => 'Status'],
            default => ['ref' => 'Paiements', 'number' => 'Référence paiement', 'date' => 'Date', 'customer' => 'Client', 'invoice' => 'Facture liée', 'method' => 'Moyen de paiement', 'amount' => 'Montant', 'currency' => 'Devise', 'status' => 'Statut'],
        };
    }

    private function clientLabels(string $locale): array
    {
        return match ($locale) {
            'ar' => ['ref' => 'العملاء', 'name' => 'اسم العميل', 'company' => 'اسم الشركة', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف', 'tax_id' => 'الرقم الضريبي', 'invoices_count' => 'عدد الفواتير', 'total_invoiced' => 'إجمالي الفوترة', 'total_paid' => 'إجمالي المدفوع', 'outstanding' => 'المبلغ المستحق'],
            'en' => ['ref' => 'Clients', 'name' => 'Client name', 'company' => 'Company name', 'email' => 'Email', 'phone' => 'Phone', 'tax_id' => 'Tax/VAT ID', 'invoices_count' => 'Number of invoices', 'total_invoiced' => 'Total invoiced', 'total_paid' => 'Total paid', 'outstanding' => 'Outstanding amount'],
            default => ['ref' => 'Clients', 'name' => 'Nom du client', 'company' => "Nom de l'entreprise", 'email' => 'E-mail', 'phone' => 'Téléphone', 'tax_id' => 'N° fiscal / TVA', 'invoices_count' => 'Nombre de factures', 'total_invoiced' => 'Total facturé', 'total_paid' => 'Total payé', 'outstanding' => 'Montant en attente'],
        };
    }
}
