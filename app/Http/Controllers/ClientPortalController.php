<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\ClientPortal\ClientPortalService;
use App\Services\ClientPortal\PortalInvoicePaymentService;
use App\Services\ClientPortal\PortalPaymentException;
use App\Services\ClientPortal\QuoteDecisionNotifier;
use App\Services\EInvoicing\EInvoiceProfileRegistry;
use App\Services\EInvoicing\InvoiceMapper;
use App\Services\EInvoicing\Ubl\UblValidationError;
use App\Services\Pdf\TemplateRendererService;
use App\Services\QuotePdfService;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Client Portal - public, read-only. No auth/password account exists for
 * a customer; a possession-based secure token
 * (App\Services\ClientPortal\ClientPortalService) is the only access
 * control. See routes/tenant.php ("Public client-facing" group, same
 * pattern already used by PaymentController::publicPayPage()) for how
 * this stays scoped to the correct tenant.
 *
 * Step 3 adds document downloads (invoicePdf/invoiceUbl/quotePdf) on top
 * of Step 1's show(). Every one of them goes through the exact same
 * resolvePortalCustomer() + ownership check before touching a document -
 * see that method's docblock for the "never reveal why" rule this
 * controller follows throughout.
 */
class ClientPortalController extends Controller
{
    /**
     * The only invoice/quote statuses a customer is ever allowed to see
     * or download - identical sets to Step 1's own invoicesPayload()/
     * quotesPayload() below, reused (not redefined) for the Step 3
     * download endpoints so "visible in the portal" and "downloadable
     * from the portal" can never drift apart. A draft is never in either
     * set - see the class docblock.
     */
    private const INVOICE_VISIBLE_STATUSES = [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED];
    // Step 4 adds accepted/rejected here too - a quote the customer just
    // acted on must stay visible/downloadable exactly like a converted or
    // cancelled one already was (see acceptQuote()/rejectQuote() below).
    private const QUOTE_VISIBLE_STATUSES = [
        Quote::STATUS_SENT,
        Quote::STATUS_CONVERTED,
        Quote::STATUS_CANCELLED,
        Quote::STATUS_ACCEPTED,
        Quote::STATUS_REJECTED,
    ];
    // The one state a quote can be acted on from - see
    // acceptQuote()/rejectQuote(). This single guard is what makes
    // "accept twice", "reject an accepted quote", "act on a
    // draft/converted/cancelled quote" etc. all fail the same way: once
    // status leaves STATUS_SENT it can never come back.
    private const QUOTE_ACTIONABLE_STATUS = Quote::STATUS_SENT;

    public function __construct(
        private ClientPortalService $portal,
        private TenantContextService $tenantContext,
        private QuoteDecisionNotifier $decisionNotifier,
    ) {
    }

    /**
     * GET /portal/{token}. Never reveals *why* a token didn't work
     * beyond the HTTP status: 404 for a token that never existed (or
     * whose customer is gone), 410 for one that existed but was
     * revoked/regenerated. Neither response differs in shape from the
     * other in any way an attacker could use to enumerate customers or
     * tokens - both are a bare abort with no body content tied to this
     * request's specific token/customer.
     */
    public function show(string $token): JsonResponse
    {
        $customer = $this->resolvePortalCustomer($token);

        return response()->json([
            'company'  => $this->companyPayload(),
            'customer' => ['name' => $customer->name],
            'summary'  => $this->summaryPayload($customer),
            'invoices' => $this->invoicesPayload($customer),
            'quotes'   => $this->quotesPayload($customer),
        ]);
    }

    /**
     * GET /portal/{token}/invoices/{invoice}/pdf. Reuses the exact same
     * renderer InvoiceController::generateInvoicePdf() uses for its
     * default/current template path - no new or duplicated invoice
     * template. The two hardcoded-hostname legacy view overrides that
     * method also has are deliberately not reproduced here: they exist
     * for two specific legacy admin domains, not a general "how an
     * invoice renders" rule, and have nothing to do with the portal.
     */
    public function invoicePdf(string $token, Invoice $invoice, TemplateRendererService $renderer): Response|JsonResponse
    {
        $customer = $this->resolvePortalCustomer($token);
        $this->authorizeInvoiceForCustomer($invoice, $customer);

        $invoice->loadMissing(['customer', 'carts']);

        try {
            $pdfContent = $renderer->render($invoice, 'invoice');
        } catch (\Throwable $e) {
            return $this->generationFailed('Client Portal invoice PDF generation failed', [
                'invoice_uuid' => $invoice->uuid,
                'exception'    => $e->getMessage(),
            ]);
        }

        $filename = 'invoice-' . $this->sanitizeFilename($invoice->displayNumber()) . '.pdf';

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($pdfContent),
        ]);
    }

    /**
     * GET /portal/{token}/invoices/{invoice}/ubl. The exact same
     * pipeline as InvoiceController::exportUbl() (Step 4/5 of the
     * e-invoicing groundwork) - InvoiceMapper -> EInvoiceProfileRegistry
     * -> the configured profile's build()/validateOutput() - just
     * authorized by the portal token instead of auth:api. No UBL logic
     * lives here; this only ever calls the existing pipeline and never
     * returns XML that failed validation.
     */
    public function invoiceUbl(string $token, Invoice $invoice, InvoiceMapper $mapper, EInvoiceProfileRegistry $profiles): Response|JsonResponse
    {
        $customer = $this->resolvePortalCustomer($token);
        $this->authorizeInvoiceForCustomer($invoice, $customer);

        $invoice->loadMissing(['customer', 'carts', 'taxLines']);

        try {
            $dto     = $mapper->map($invoice);
            $profile = $profiles->get();
            $xml     = $profile->build($dto);
            $result  = $profile->validateOutput($xml);
        } catch (\Throwable $e) {
            return $this->generationFailed('Client Portal UBL export failed while building XML', [
                'invoice_uuid' => $invoice->uuid,
                'exception'    => $e->getMessage(),
            ]);
        }

        if (!$result->valid) {
            return $this->generationFailed('Client Portal UBL export produced XML that failed the official UBL 2.1 XSD', [
                'invoice_uuid' => $invoice->uuid,
                'errors'       => array_map(fn (UblValidationError $e) => $e->toArray(), $result->errors),
            ]);
        }

        $filename = 'invoice-' . $this->sanitizeFilename($invoice->displayNumber()) . '.xml';

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($xml),
        ]);
    }

    /**
     * GET /portal/{token}/quotes/{quote}/pdf. Reuses
     * QuotePdfService::generate() - the exact same service
     * QuoteController::downloadPdf() already uses - no new/duplicated
     * quote template.
     */
    public function quotePdf(string $token, Quote $quote, QuotePdfService $pdfService): Response|JsonResponse
    {
        $customer = $this->resolvePortalCustomer($token);
        $this->authorizeQuoteForCustomer($quote, $customer);

        $quote->load('customer', 'carts');

        try {
            $pdfContent = $pdfService->generate($quote);
        } catch (\Throwable $e) {
            return $this->generationFailed('Client Portal quote PDF generation failed', [
                'quote_uuid' => $quote->uuid,
                'exception'  => $e->getMessage(),
            ]);
        }

        $filename = 'quote-' . $this->sanitizeFilename((string) ($quote->reference ?? '')) . '.pdf';

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($pdfContent),
        ]);
    }

    /**
     * POST /portal/{token}/quotes/{quote}/accept. Only ever moves a quote
     * out of STATUS_SENT - see QUOTE_ACTIONABLE_STATUS. Does not touch
     * invoices at all: accepting a quote here never creates one (that
     * stays a manual, staff-initiated action via the existing
     * QuoteController::convert()/QuoteToInvoiceService, unchanged by
     * this step).
     */
    public function acceptQuote(string $token, Quote $quote): JsonResponse
    {
        $customer = $this->resolvePortalCustomer($token);
        $this->authorizeQuoteForCustomer($quote, $customer);

        if (!$this->transitionQuote($quote, Quote::STATUS_ACCEPTED, 'accepted_at')) {
            return response()->json(['message' => __('quote.portal_cannot_accept')], 422);
        }

        return response()->json([
            'message'     => __('quote.portal_accepted'),
            'status'      => $quote->status,
            // Same toDateString() format quotesPayload() below already
            // uses, so a value patched in from this response and one
            // read back from a later GET /portal/{token} never disagree.
            'accepted_at' => $quote->accepted_at?->toDateString(),
        ]);
    }

    /**
     * POST /portal/{token}/quotes/{quote}/reject. Symmetric with
     * acceptQuote() above - same actionable-state guard, same "never
     * touches an invoice" rule.
     */
    public function rejectQuote(string $token, Quote $quote): JsonResponse
    {
        $customer = $this->resolvePortalCustomer($token);
        $this->authorizeQuoteForCustomer($quote, $customer);

        if (!$this->transitionQuote($quote, Quote::STATUS_REJECTED, 'rejected_at')) {
            return response()->json(['message' => __('quote.portal_cannot_reject')], 422);
        }

        return response()->json([
            'message'     => __('quote.portal_rejected'),
            'status'      => $quote->status,
            'rejected_at' => $quote->rejected_at?->toDateString(),
        ]);
    }

    /**
     * POST /portal/{token}/invoices/{invoice}/payment/stripe (Step 6A).
     * Takes no request body at all - the amount (stored invoice total),
     * currency (tenant currency) and connected account (tenant's
     * CompanyProfile) are all decided server-side. Returns only the Stripe
     * Checkout URL to redirect to. The success URL is UX only: the invoice
     * is marked paid exclusively by the signed Connect webhook
     * (StripeConnectWebhookController).
     */
    public function startInvoicePayment(string $token, Invoice $invoice, PortalInvoicePaymentService $payments): JsonResponse
    {
        $customer = $this->resolvePortalCustomer($token);
        $this->authorizeInvoiceForCustomer($invoice, $customer);

        $portalUrl = request()->getSchemeAndHttpHost() . '/portal/' . rawurlencode($token);

        try {
            $url = $payments->startCheckout($invoice, $portalUrl . '?payment=success', $portalUrl . '?payment=cancelled');
        } catch (PortalPaymentException $e) {
            return response()->json([
                'reason'  => $e->reason,
                'message' => __('invoice.portal_payment.' . $e->reason),
            ], $e->status);
        } catch (\Throwable $e) {
            Log::error('Client Portal Stripe Checkout creation failed', [
                'invoice_uuid' => $invoice->uuid,
                'exception'    => $e->getMessage(),
            ]);

            return response()->json([
                'reason'  => 'unavailable',
                'message' => __('invoice.portal_payment.unavailable'),
            ], 502);
        }

        return response()->json(['checkout_url' => $url]);
    }

    /**
     * Moves a quote out of QUOTE_ACTIONABLE_STATUS with a single
     * conditional UPDATE (WHERE status = 'sent'), so two concurrent
     * submissions (double click, two tabs) can never both succeed - the
     * second one matches zero rows and is refused like any other
     * already-decided quote. Returns false when nothing was updated.
     */
    private function transitionQuote(Quote $quote, string $status, string $timestampColumn): bool
    {
        $now = now();

        $updated = Quote::where('id', $quote->id)
            ->where('status', self::QUOTE_ACTIONABLE_STATUS)
            ->update([
                'status'         => $status,
                $timestampColumn => $now,
                'updated_at'     => $now,
            ]);

        if ($updated === 0) {
            return false;
        }

        $quote->refresh();

        // Step 5 - tell the company. Only reached by the one request that
        // actually made the transition, so it never sends twice.
        $this->decisionNotifier->notify($quote);

        return true;
    }

    /**
     * The one place every portal endpoint (show() and the three download
     * actions above) resolves a token: 404 for a token that never
     * existed or whose customer is gone, 410 for one that existed but
     * was revoked/regenerated - never anything more specific than that,
     * and never a different response shape between the two. A
     * successful resolution always records the access (last_accessed_at).
     */
    private function resolvePortalCustomer(string $token): Customer
    {
        $access = $this->portal->resolveAccess($token);

        if (!$access) {
            abort(404);
        }

        if ($access->isRevoked()) {
            abort(410);
        }

        $customer = $access->customer;

        if (!$customer) {
            abort(404);
        }

        $this->portal->recordAccess($access);

        return $customer;
    }

    /**
     * Ownership + visibility, both collapsed into the same bare 404: a
     * document belonging to a different customer and a document that
     * genuinely doesn't exist yet (a draft) must be indistinguishable
     * from the outside - see the class docblock.
     */
    private function authorizeInvoiceForCustomer(Invoice $invoice, Customer $customer): void
    {
        if ((int) $invoice->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if (!in_array($invoice->status, self::INVOICE_VISIBLE_STATUSES, true)) {
            abort(404);
        }
    }

    private function authorizeQuoteForCustomer(Quote $quote, Customer $customer): void
    {
        if ((int) $quote->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if (!in_array($quote->status, self::QUOTE_VISIBLE_STATUSES, true)) {
            abort(404);
        }
    }

    /**
     * Logs the real, technical reason server-side and answers with
     * Fakturalista's own generic, translated message - never a raw
     * exception message or libxml/PDF-library detail.
     */
    private function generationFailed(string $logMessage, array $context): JsonResponse
    {
        Log::error($logMessage, $context);

        return response()->json(['message' => __('invoice.actions.pdf_export_failed')], 500);
    }

    /**
     * Keeps only characters safe in a filename across OSes/browsers -
     * same rule App\Http\Controllers\InvoiceController::sanitizeUblFilename()
     * already applies to its own UBL download.
     */
    private function sanitizeFilename(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '';
        $safe = trim($safe, '-');

        return $safe !== '' ? $safe : 'document';
    }

    /**
     * Only what a customer-facing portal header needs - never the
     * tenant's tax IDs, address, email, phone, or any other
     * CompanyProfile field.
     */
    private function companyPayload(): array
    {
        $company = CompanyProfile::first();

        return [
            'name'        => $company?->trade_name ?: ($company?->legal_name ?: config('app.name')),
            'logo_url'    => $company?->logo_path ? Storage::url($company->logo_path) : null,
            'brand_color' => $company?->brand_color ?: null,
        ];
    }

    /**
     * Same "issued and unpaid" / "issued and past its due date" logic
     * InvoicePaymentsController::summary()/payable() already use for the
     * admin dashboard - not reinvented here, just scoped to one customer.
     */
    private function summaryPayload(Customer $customer): array
    {
        $today = now()->toDateString();

        $unpaid = Invoice::where('customer_id', $customer->id)
            ->where('status', Invoice::STATUS_ISSUED);

        return [
            'total_outstanding'     => (float) (clone $unpaid)->sum('total'),
            'overdue_amount'        => (float) (clone $unpaid)->where('expiration_date', '<', $today)->sum('total'),
            'unpaid_invoices_count' => (clone $unpaid)->count(),
        ];
    }

    /**
     * Draft invoices are internal (never sent to the customer) and are
     * deliberately excluded - only issued/paid/cancelled documents, the
     * same set InvoicePaymentsController::index() treats as "real"
     * invoices for a customer to see.
     */
    private function invoicesPayload(Customer $customer): array
    {
        $currency = $this->tenantContext->currency();
        $payments = app(PortalInvoicePaymentService::class);
        // Seller-level Stripe Connect readiness, checked once per request.
        $sellerAcceptsPayments = $payments->sellerAcceptsPayments();

        return Invoice::where('customer_id', $customer->id)
            ->whereIn('status', self::INVOICE_VISIBLE_STATUSES)
            ->orderByDesc('date')
            ->get()
            ->map(function (Invoice $invoice) use ($currency, $payments, $sellerAcceptsPayments) {
                $payable = $payments->isPayable($invoice, $sellerAcceptsPayments);

                return [
                    'uuid'       => $invoice->uuid,
                    'number'     => $invoice->displayNumber(),
                    'issue_date' => $invoice->date,
                    'due_date'   => $invoice->expiration_date,
                    'total'      => (float) ($invoice->total ?? 0),
                    'status'     => $invoice->status,
                    'currency'   => $currency,
                    // Step 6B - date only, and only once paid.
                    'paid_at'    => $invoice->isPaid() ? $invoice->paid_at?->toDateString() : null,
                    // Step 6B - display capability only; the Checkout
                    // endpoint re-checks everything. Never any Stripe
                    // account, attempt or failure detail.
                    'payment'    => [
                        'payable'  => $payable,
                        'provider' => $payable ? 'stripe' : null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * Draft quotes are internal (never sent to the customer) and are
     * deliberately excluded, for the same reason as draft invoices above.
     */
    private function quotesPayload(Customer $customer): array
    {
        $currency = $this->tenantContext->currency();

        return Quote::where('customer_id', $customer->id)
            ->whereIn('status', self::QUOTE_VISIBLE_STATUSES)
            ->orderByDesc('date')
            ->get()
            ->map(fn (Quote $quote) => [
                'uuid'        => $quote->uuid,
                'number'      => (string) ($quote->reference ?? ''),
                'date'        => $quote->date,
                'total'       => (float) ($quote->total ?? 0),
                'status'      => $quote->status,
                'currency'    => $currency,
                'accepted_at' => $quote->accepted_at?->toDateString(),
                'rejected_at' => $quote->rejected_at?->toDateString(),
            ])
            ->all();
    }
}
