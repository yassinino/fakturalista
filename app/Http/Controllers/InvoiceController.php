<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoiceTaxLine;
use App\Models\Customer;
use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Http\Requests\InvoiceRequest;
use App\Mail\InvoiceEmail;
use App\Services\InvoiceNumberingService;
use App\Services\InvoiceRectificationService;
use App\Services\Pdf\TemplateRendererService;
use App\Services\PlanService;
use App\Services\Tax\DocumentCalculationResult;
use App\Services\Tax\DocumentCalculationService;
use App\Services\Tax\TaxTreatment;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Storage;

class InvoiceController extends Controller
{
    public function __construct(
        private TemplateRendererService $renderer,
        private PlanService $planLimits,
        private InvoiceNumberingService $numbering,
        private InvoiceRectificationService $rectifications,
        private TenantContextService $tenantContext,
        private DocumentCalculationService $calculator,
    ) {}

    // ── Listing ────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $perPage  = request()->input('per_page', 10);
        $search   = request()->input('search');
        $status   = request()->input('status');
        $dateFrom = request()->input('date_from');
        $dateTo   = request()->input('date_to');
        $sortBy   = request()->input('sort_by', 'created_at');
        $sortDir  = request()->input('sort_dir', 'desc');

        $allowedSorts = ['date', 'total', 'status', 'created_at', 'reference'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = Invoice::with('customer');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $paginator->getCollection()->transform(function ($invoice) {
            return [
                'checked'         => false,
                'uuid'            => $invoice->uuid,
                'customer'        => $invoice->customer?->name,
                'customer_email'  => $invoice->customer?->email,
                'customer_phone'  => $invoice->customer?->phone,
                'reference'       => $invoice->reference,
                'date'            => $invoice->date,
                'expiration_date' => $invoice->expiration_date,
                'status'          => $invoice->status,
                'sub_total'       => $invoice->sub_total,
                'total'           => $invoice->total,
                'is_locked'       => $invoice->isLocked(),
            ];
        });

        $company     = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        return response()->json([
            'invoices'     => $paginator->items(),
            'company_name' => $companyName,
            'meta'         => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function create()
    {
        //
    }

    // ── Store ──────────────────────────────────────────────

    public function store(InvoiceRequest $request): JsonResponse
    {
        if (!$this->planLimits->canCreateInvoice()) {
            $plan  = $this->planLimits->currentPlan();
            $limit = $this->planLimits->getLimit('invoices_per_month');

            return response()->json([
                'error'      => 'plan_limit_reached',
                'resource'   => 'invoice',
                'limit'      => $limit,
                'used'       => $this->planLimits->invoicesThisMonth(),
                'resets_at'  => $this->planLimits->invoiceQuotaResetsAt(),
                'plan_name'  => $plan ? $plan->translate('name') : 'Starter',
                'plan_slug'  => $plan?->slug ?? 'starter',
            ], 402);
        }

        $customer = Customer::where('uuid', $request->customer_id)->firstOrFail();

        // Morocco Phase 1C.1 (docs/morocco-phase-1c1-generic-tax-foundation.md):
        // the server recomputes every financial figure from the submitted
        // lines - sub_total/discount_amount/vta/vta4/vta10/vta21/total
        // sent by the client are never trusted as authoritative.
        $carts       = $request->carts ?? [];
        $calculation = $this->calculator->calculate(
            $this->buildCalculationLines($carts),
            (float) ($request->discount_rate ?? 0)
        );
        $legacyRates = $calculation->legacySpanishRateAmounts();

        $invoice = DB::transaction(function () use ($request, $customer, $carts, $calculation, $legacyRates) {
            $invoice = Invoice::create([
                'uuid'            => Str::uuid()->toString(),
                'reference'       => $this->numbering->nextDraftLabel(),
                'customer_id'     => $customer->id,
                'date'            => $request->date,
                'status'          => Invoice::STATUS_DRAFT,
                'expiration_date' => date('Y-m-d', strtotime($request->expiration_date)),
                'payment_terms'   => $request->payment_terms,
                'sub_total'       => $calculation->subTotal,
                'discount_rate'   => $request->discount_rate,
                'discount_amount' => $calculation->discountAmount,
                'vta'             => $calculation->totalTax,
                'vta4'            => $legacyRates['vta4'],
                'vta10'           => $legacyRates['vta10'],
                'vta21'           => $legacyRates['vta21'],
                'total'           => $calculation->grandTotal,
                'note'            => $request->note,
                'descripcion_operacion' => $request->descripcion_operacion,
            ]);

            $this->syncCarts($invoice->id, $carts, $calculation);
            $this->persistTaxBreakdown($invoice, $calculation);
            $invoice->logHistory(InvoiceHistory::ACTION_CREATED);

            return $invoice;
        });

        return response()->json([
            'message' => __('invoice.actions.created'),
            // Authoritative values, for a caller that wants to display them
            // immediately instead of waiting for the next show()/edit() -
            // the frontend's own totals remain a preview only (Morocco
            // Phase 1C.1 §11).
            'invoice' => $this->calculationResponse($invoice, $calculation),
        ], 200);
    }

    // ── Read ───────────────────────────────────────────────

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(['invoice' => $invoice->load('customer', 'carts')]);
    }

    public function edit(Invoice $invoice): JsonResponse
    {
        $customer    = Customer::withTrashed()->where('id', $invoice->customer_id)->firstOrFail();
        $latestSent  = $invoice->history()->where('action', InvoiceHistory::ACTION_SENT)->latest()->first();
        $company     = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        return response()->json([
            'invoice' => [
                'id'                => $invoice->id,
                'uuid'              => $invoice->uuid,
                'reference'         => $invoice->reference,
                'customer_id'       => $customer->uuid,
                'customer_name'     => $customer->name,
                'customer_email'    => $customer->email,
                'customer_phone'    => $customer->phone,
                'company_name'      => $companyName,
                'address'           => $customer->address_billing,
                'date'              => $invoice->date,
                'status'            => $invoice->status,
                'issued_at'         => $invoice->issued_at,
                'source_invoice_id' => $invoice->source_invoice_id,
                'is_locked'         => $invoice->isLocked(),
                'expiration_date'   => $invoice->expiration_date,
                'payment_terms'     => $invoice->payment_terms,
                'sub_total'         => $invoice->sub_total,
                'discount_rate'     => $invoice->discount_rate,
                'discount_amount'   => $invoice->discount_amount,
                'vta'               => $invoice->vta,
                'total'             => $invoice->total,
                'note'              => $invoice->note,
                'descripcion_operacion' => $invoice->descripcion_operacion,
                'carts'             => $invoice->carts,
                'sent_at'           => $latestSent?->created_at,
                'sent_to'           => $latestSent?->context['to'] ?? null,
                'paid_at'           => $invoice->paid_at,
                'paid_via'          => $invoice->paid_via,
            ],
        ]);
    }

    // ── Update ─────────────────────────────────────────────

    public function update(InvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->isLocked()) {
            return response()->json([
                'message' => __('invoice.actions.locked_cannot_edit'),
            ], 403);
        }

        $customer = Customer::where('uuid', $request->customer_id)->firstOrFail();

        $carts       = $request->carts ?? [];
        $calculation = $this->calculator->calculate(
            $this->buildCalculationLines($carts),
            (float) ($request->discount_rate ?? 0)
        );
        $legacyRates = $calculation->legacySpanishRateAmounts();

        Invoice::where('id', $invoice->id)->update([
            'customer_id'     => $customer->id,
            'date'            => $request->date,
            'expiration_date' => $request->expiration_date,
            'payment_terms'   => $request->payment_terms,
            'sub_total'       => $calculation->subTotal,
            'discount_rate'   => $request->discount_rate,
            'discount_amount' => $calculation->discountAmount,
            'vta'             => $calculation->totalTax,
            'vta4'            => $legacyRates['vta4'],
            'vta10'           => $legacyRates['vta10'],
            'vta21'           => $legacyRates['vta21'],
            'total'           => $calculation->grandTotal,
            'note'            => $request->note,
            'descripcion_operacion' => $request->descripcion_operacion,
        ]);

        $this->syncCarts($invoice->id, $carts, $calculation);
        $this->persistTaxBreakdown($invoice, $calculation);

        return response()->json([
            'message' => __('invoice.actions.updated'),
            'invoice' => $this->calculationResponse($invoice->fresh(), $calculation),
        ], 200);
    }

    // ── Lifecycle Actions ──────────────────────────────────

    public function issue(Invoice $invoice): JsonResponse
    {
        try {
            $this->issueInvoice($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'   => __('invoice.actions.issued'),
            'reference' => $invoice->reference,
            'status'    => $invoice->status,
            'issued_at' => $invoice->issued_at,
        ], 200);
    }

    public function markPaid(Invoice $invoice): JsonResponse
    {
        if (!$invoice->isIssued()) {
            return response()->json([
                'message' => __('invoice.actions.mark_paid_requires_issued', ['status' => $invoice->status]),
            ], 422);
        }

        $invoice->status = Invoice::STATUS_PAID;
        $invoice->save();

        $invoice->logHistory(InvoiceHistory::ACTION_PAID);

        return response()->json(['message' => __('invoice.actions.marked_paid'), 'status' => $invoice->status], 200);
    }

    /**
     * Cancel an invoice via the AEAT "registro de anulación" mechanism.
     *
     * Only accepts reasons that resolve to the "anulación" mechanism (see
     * InvoiceRectificationService) - i.e. cases where no real underlying
     * operation existed. A reason that legally requires a rectificative
     * invoice instead is rejected here, guiding the caller to POST
     * /invoices/{invoice}/rectify instead of silently doing the wrong thing.
     */
    public function cancel(Invoice $invoice, Request $request): JsonResponse
    {
        if ($invoice->isCancelled()) {
            return response()->json(['message' => __('invoice.actions.already_cancelled')], 422);
        }

        if ($invoice->isDraft()) {
            return response()->json(['message' => __('invoice.actions.draft_deleted_not_cancelled')], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|in:' . implode(',', InvoiceRectificationService::REASONS),
            // Optional, symmetric with /rectify's "mode" - lets a caller who
            // has already confirmed "otro" means anulación say so directly,
            // instead of being bounced to /rectify just to be bounced back.
            'mode'   => 'nullable|string|in:' . InvoiceRectificationService::MECHANISM_RECTIFICATIVA . ',' . InvoiceRectificationService::MECHANISM_ANULACION,
        ]);

        try {
            $mechanism = $this->rectifications->determineMechanism($validated['reason'], $validated['mode'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($mechanism === InvoiceRectificationService::MECHANISM_REQUIRES_REVIEW) {
            return response()->json([
                'message' => __('invoice.actions.requires_review'),
                'requires_review' => true,
            ], 422);
        }

        if ($mechanism !== InvoiceRectificationService::MECHANISM_ANULACION) {
            return response()->json([
                'message' => __('invoice.actions.suggests_rectify'),
                'suggested_mechanism' => $mechanism,
            ], 422);
        }

        $invoice->status              = Invoice::STATUS_CANCELLED;
        $invoice->cancellation_reason = $validated['reason'];
        $invoice->save();

        $invoice->logHistory(InvoiceHistory::ACTION_CANCELLED, ['reason' => $validated['reason']]);

        return response()->json(['message' => __('invoice.actions.cancelled'), 'status' => $invoice->status], 200);
    }

    /**
     * Create a rectificative invoice against $invoice.
     *
     * Only accepts reasons that resolve to the "rectificativa" mechanism -
     * a reason meaning "there was no real operation" is rejected here and
     * pointed at /invoices/{invoice}/cancel instead.
     */
    public function rectify(Invoice $invoice, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason'             => 'required|string|in:' . implode(',', InvoiceRectificationService::REASONS),
            'mode'               => 'nullable|string|in:' . InvoiceRectificationService::MECHANISM_RECTIFICATIVA . ',' . InvoiceRectificationService::MECHANISM_ANULACION,
            'rectification_type' => 'required|string|in:' . Invoice::RECTIFICATION_MODE_SUSTITUCION . ',' . Invoice::RECTIFICATION_MODE_DIFERENCIAS,
            // Advanced path: an accountant who has already confirmed the
            // real cause (e.g. an insolvency or bad-debt procedure) can
            // pass the exact AEAT code directly, reaching R2/R3 without
            // this service ever guessing them from a business reason.
            'invoice_type'       => 'nullable|string|in:' . implode(',', Invoice::RECTIFICATION_TYPES),
        ]);

        try {
            $mechanism = $this->rectifications->determineMechanism($validated['reason'], $validated['mode'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($mechanism === InvoiceRectificationService::MECHANISM_REQUIRES_REVIEW) {
            return response()->json([
                'message' => __('invoice.actions.requires_review'),
                'requires_review' => true,
            ], 422);
        }

        if ($mechanism !== InvoiceRectificationService::MECHANISM_RECTIFICATIVA) {
            return response()->json([
                'message' => __('invoice.actions.suggests_cancel'),
                'suggested_mechanism' => $mechanism,
            ], 422);
        }

        try {
            $rectification = $this->rectifications->createRectification(
                $invoice,
                $validated['reason'],
                $validated['rectification_type'],
                $validated['invoice_type'] ?? null,
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'             => __('invoice.actions.rectification_created'),
            'rectification_uuid'  => $rectification->uuid,
            'reference'           => $rectification->reference,
        ], 200);
    }

    /**
     * Duplicate an invoice as a new draft with a new invoice number.
     * Uses nextReference() inside a transaction so the number is unique
     * and race-condition-safe, reusing the same numbering logic as store().
     */
    public function duplicate(Invoice $invoice): JsonResponse
    {
        $sourceInvoice = $invoice;
        $sourceInvoice->load('carts');

        // Recomputed via the same authoritative calculator, from the
        // copied cart lines, rather than copying the source's own stored
        // totals verbatim - Morocco Phase 1C.1. For a source invoice
        // already computed by this engine the numbers are identical; for
        // a legacy invoice predating it, the new draft starts from a
        // clean, consistent calculation instead of inheriting whatever
        // the old client-trusted totals happened to be.
        $lines = $sourceInvoice->carts->map(fn (Cart $cart) => [
            'quantity'   => $cart->qty,
            'unit_price' => $cart->price,
            'discount'   => $cart->discount,
            'tax_rate'   => $cart->vta,
            'treatment'  => $cart->tax_treatment ?? TaxTreatment::TAXABLE,
        ])->all();
        $calculation = $this->calculator->calculate($lines, (float) ($sourceInvoice->discount_rate ?? 0));
        $legacyRates = $calculation->legacySpanishRateAmounts();

        $duplicate = DB::transaction(function () use ($sourceInvoice, $calculation, $legacyRates) {
            $duplicate = Invoice::create([
                'uuid'              => Str::uuid()->toString(),
                'reference'         => $this->numbering->nextDraftLabel(),
                'customer_id'       => $sourceInvoice->customer_id,
                'date'              => now()->toDateString(),
                'status'            => Invoice::STATUS_DRAFT,
                'expiration_date'   => now()->addDays(30)->toDateString(),
                'payment_terms'     => $sourceInvoice->payment_terms,
                'sub_total'         => $calculation->subTotal,
                'discount_rate'     => $sourceInvoice->discount_rate,
                'discount_amount'   => $calculation->discountAmount,
                'vta'               => $calculation->totalTax,
                'vta4'              => $legacyRates['vta4'],
                'vta10'             => $legacyRates['vta10'],
                'vta21'             => $legacyRates['vta21'],
                'total'             => $calculation->grandTotal,
                'note'              => $sourceInvoice->note,
                'descripcion_operacion' => $sourceInvoice->descripcion_operacion,
                'source_invoice_id' => $sourceInvoice->id,
            ]);

            foreach ($sourceInvoice->carts as $index => $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Invoice',
                    'cartable_id'   => $duplicate->id,
                    'item_id'       => $cart->item_id,
                    'description'   => $cart->description,
                    'qty'           => $cart->qty,
                    'price'         => $cart->price,
                    'unite'         => $cart->unite,
                    'discount'      => $cart->discount,
                    'total'         => $calculation->lines[$index]['taxable_base'] ?? $cart->total,
                    'vta'           => $cart->vta,
                    'tax_treatment' => $cart->tax_treatment ?? TaxTreatment::TAXABLE,
                ]);
            }

            $this->persistTaxBreakdown($duplicate, $calculation);

            $sourceInvoice->logHistory(InvoiceHistory::ACTION_DUPLICATED, [
                'duplicate_uuid' => $duplicate->uuid,
            ]);
            $duplicate->logHistory(InvoiceHistory::ACTION_CREATED, [
                'source_uuid' => $sourceInvoice->uuid,
            ]);

            return $duplicate;
        });

        return response()->json([
            'message'        => __('invoice.actions.duplicated'),
            'duplicate_uuid' => $duplicate->uuid,
            'reference'      => $duplicate->reference,
        ], 200);
    }

    public function history(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'history' => $invoice->history()->get(['action', 'context', 'created_at']),
        ]);
    }

    // ── Send invoice by email ──────────────────────────────

    public function send(Invoice $invoice): JsonResponse
    {
        $invoice->load('customer', 'carts.product');
        $customer = $invoice->customer;

        if ($invoice->isCancelled()) {
            return response()->json([
                'message' => __('invoice.actions.cannot_send_cancelled'),
            ], 422);
        }

        if (empty($customer?->email)) {
            return response()->json([
                'message' => __('invoice.actions.customer_missing_email'),
            ], 422);
        }

        // Auto-issue if still a draft - goes through the same numbering +
        // fiscal-data validation as an explicit "issue" action.
        if ($invoice->isDraft()) {
            try {
                $this->issueInvoice($invoice);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $pdfContent    = $this->generateInvoicePdf($invoice);
        $customMessage = request()->input('custom_message') ?: null;

        $company     = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        // Include a Stripe payment link if configured
        $paymentUrl = null;
        if (!empty(config('services.stripe.secret'))) {
            $paymentUrl = request()->getSchemeAndHttpHost() . '/pay/' . $invoice->uuid;
        }

        try {
            Mail::to($customer->email, $customer->name)->send(new InvoiceEmail(
                invoice:       $invoice,
                customerName:  $customer->name,
                companyName:   $companyName,
                companyEmail:  $company?->email,
                companyPhone:  $company?->phone,
                pdfContent:    $pdfContent,
                customMessage: $customMessage,
                paymentUrl:    $paymentUrl,
            ));
        } catch (\Exception $e) {
            return response()->json([
                'message' => __('invoice.actions.email_send_failed'),
                'status'  => $invoice->status,
            ], 500);
        }

        $sentAt = now();
        $invoice->logHistory(InvoiceHistory::ACTION_SENT, ['to' => $customer->email]);

        return response()->json([
            'message' => __('invoice.actions.sent_to', ['email' => $customer->email]),
            'status'  => $invoice->status,
            'sent_to' => $customer->email,
            'sent_at' => $sentAt,
        ], 200);
    }

    // ── WhatsApp wa.me link ────────────────────────────────

    /**
     * Build a wa.me click-to-chat link for this invoice.
     *
     * Phone normalization: strips +, 00-prefix, and all non-digits.
     * Numbers stored in full international format (e.g. +34 600 123 456)
     * normalize correctly to 34600123456.  Numbers stored without a country
     * code (e.g. 600 123 456) will be missing the prefix - wa.me may open
     * but target the wrong number.  Recommend storing phones as +<country><number>.
     */
    public function whatsapp(Invoice $invoice): JsonResponse
    {
        $invoice->load('customer', 'carts.product');
        $customer = $invoice->customer;

        if ($invoice->isCancelled()) {
            return response()->json([
                'message' => __('invoice.actions.cannot_share_cancelled'),
            ], 422);
        }

        if (empty($customer?->phone)) {
            return response()->json([
                'message' => __('invoice.actions.customer_missing_phone'),
            ], 422);
        }

        // Auto-issue draft so the PDF has a final reference number - goes
        // through the same numbering + fiscal-data validation as an
        // explicit "issue" action.
        if ($invoice->isDraft()) {
            try {
                $this->issueInvoice($invoice);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        // Generate and save the PDF so it has a stable public URL
        $pdfContent = $this->generateInvoicePdf($invoice);
        $filePath   = 'invoices/invoice_' . $invoice->uuid . '.pdf';
        Storage::disk('public')->put($filePath, $pdfContent);
        $pdfUrl = request()->getSchemeAndHttpHost() . Storage::url($filePath);

        $locale        = app()->getLocale();
        $formatter     = app(\App\Services\CurrencyFormatter::class);
        $formattedTotal = $formatter->format((float) $invoice->total, $this->tenantContext->currency(), $locale);

        // Amount paid / remaining balance - invoices here are binary
        // paid/not-paid (no partial-payment tracking exists in this app),
        // so "paid" means the full total, "not paid" means the full total
        // is still owed. See InvoicePaymentsController for the same model.
        $balanceLine = $invoice->isPaid()
            ? __('invoice.actions.whatsapp_balance_paid')
            : __('invoice.actions.whatsapp_balance_due', ['balance' => $formattedTotal]);

        $greetingName = ($customer->isIndividual() && $customer->first_name)
            ? $customer->first_name
            : $customer->name;

        $phone   = $this->normalizeWhatsAppNumber($customer->phone);
        $message = __('invoice.actions.whatsapp_message', [
            'client'    => $greetingName,
            'reference' => $invoice->reference,
            'total'     => $formattedTotal,
            'balance'   => $balanceLine,
            'link'      => $pdfUrl,
        ]);

        // Append payment link if Stripe is configured
        if (!empty(config('services.stripe.secret'))) {
            $payLink  = request()->getSchemeAndHttpHost() . '/pay/' . $invoice->uuid;
            $message .= __('invoice.actions.whatsapp_pay_online_suffix', ['link' => $payLink]);
        }
        $waLink  = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);

        $invoice->logHistory(InvoiceHistory::ACTION_WHATSAPP, [
            'to'    => $customer->phone,
            'phone' => $phone,
        ]);

        return response()->json([
            'wa_link'  => $waLink,
            'pdf_url'  => $pdfUrl,
            'phone'    => $phone,
            'status'   => $invoice->status,
        ], 200);
    }

    // ── PDF ────────────────────────────────────────────────

    public function print_invoice(Request $request): JsonResponse
    {
        $invoice = Invoice::where('uuid', $request->uuid)
            ->with('customer', 'carts.product')
            ->firstOrFail();

        $pdfContent = $this->generateInvoicePdf($invoice);
        $fileName   = 'invoice_' . $invoice->uuid . '.pdf';
        $filePath   = 'invoices/' . $fileName;

        Storage::disk('public')->put($filePath, $pdfContent);

        return response()->json([
            'message' => __('invoice.actions.printed'),
            'pdf_url' => Storage::url($filePath),
        ], 200);
    }

    // ── Delete ─────────────────────────────────────────────

    /**
     * Only draft invoices can be deleted. Once an invoice has been issued
     * (or later paid/cancelled), it is a legal document and must be
     * corrected via a rectificative invoice or an anulación instead - never
     * silently removed.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        if (!$invoice->isDraft()) {
            return response()->json([
                'message' => __('invoice.actions.locked_cannot_delete'),
            ], 403);
        }

        Invoice::where('uuid', $invoice->uuid)->delete();

        return response()->json(['message' => __('invoice.actions.deleted')], 200);
    }

    /**
     * Deletes only the draft invoices among the given ids; non-draft
     * invoices are skipped (never deleted) and reported back so the UI can
     * tell the user which ones were skipped and why.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $invoices = Invoice::whereIn('uuid', $validated['ids'])->get(['id', 'uuid', 'status']);

        $deletable = $invoices->filter(fn (Invoice $invoice) => $invoice->isDraft());
        $skipped   = $invoices->reject(fn (Invoice $invoice) => $invoice->isDraft());

        if ($deletable->isNotEmpty()) {
            Invoice::whereIn('id', $deletable->pluck('id'))->delete();
        }

        $message = $skipped->isNotEmpty()
            ? __('invoice.actions.bulk_deleted_with_skipped', ['count' => $deletable->count(), 'skipped' => $skipped->count()])
            : __('invoice.actions.bulk_deleted', ['count' => $deletable->count()]);

        return response()->json([
            'message'      => $message,
            'deleted'      => $deletable->pluck('uuid')->values(),
            'skipped'      => $skipped->pluck('uuid')->values(),
        ]);
    }

    // ── Private helpers ────────────────────────────────────

    /**
     * Assign the definitive legal number and move a draft to `issued`, all
     * inside one transaction - an invoice must never end up `issued`
     * without a legal number, and a failed validation must never leave it
     * half-issued.
     *
     * @throws \RuntimeException on an invalid state transition or missing
     *         mandatory fiscal data (caller maps this to a 422 response).
     */
    private function issueInvoice(Invoice $invoice): void
    {
        if (!$invoice->isDraft()) {
            throw new \RuntimeException(__('invoice.actions.issue_invalid_state', ['status' => $invoice->status]));
        }

        $company  = CompanyProfile::first();
        $customer = $invoice->customer ?: Customer::withTrashed()->find($invoice->customer_id);

        // Requiring the issuer's own NIF/CIF to issue at all is a Spanish
        // assumption (a Moroccan company's equivalent identity is ICE, not
        // tax_id, and this phase deliberately does not invent a "must have
        // ICE" requirement - see docs/morocco-phase-1b-identity.md §3).
        // Scoped to Spain only, same boundary as the F1/customer-NIF check
        // below (Morocco Phase 1A.1/1B).
        if ($this->tenantContext->isSpain() && empty($company?->tax_id)) {
            throw new \RuntimeException(__('invoice.actions.spain_company_missing_tax_id'));
        }

        // Fakturalista only issues complete invoices (F1) by default today
        // (see the Phase 2A report) - a complete invoice requires the
        // recipient's tax ID (RD 1619/2012 art. 6). A simplified invoice
        // (F2) would not require it, but there is no UI path to choose F2
        // yet, so every standard issue() call resolves to F1. RD 1619/2012
        // is Spanish law - this check is scoped to Spanish tenants only
        // (Morocco Phase 1A.1, docs/morocco-phase-1a1-country-boundary-cleanup.md).
        // Morocco's own customer-identity rules are a separate, not-yet-defined
        // concern for Phase 1B - this is not a stand-in for them.
        $resolvedType = $invoice->invoice_type ?: Invoice::TYPE_F1;
        if ($resolvedType === Invoice::TYPE_F1 && $this->tenantContext->isSpain() && empty($customer?->tax_id)) {
            throw new \RuntimeException(__('invoice.actions.spain_customer_missing_tax_id'));
        }

        DB::transaction(function () use ($invoice, $company, $customer) {
            // Rectificative invoices must be numbered from their own
            // separate series (RD 1619/2012 art. 6.5: "será obligatoria,
            // en todo caso, la expedición en series específicas de ... las
            // rectificativas") - never from the ordinary invoice series.
            if ($invoice->isRectification()) {
                $this->numbering->assignRectificationNumber($invoice, $company);
            } else {
                $this->numbering->assignLegalNumber($invoice, $company);
            }

            $invoice->status    = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            // Generic, country-agnostic identity snapshot (Morocco Phase 1B,
            // docs/morocco-phase-1b-identity.md §7) - written once, here,
            // never updated again, so a later edit to Settings/the customer
            // can never change what an already-issued invoice shows.
            $invoice->company_snapshot  = $company->identitySnapshot();
            $invoice->customer_snapshot = $customer?->identitySnapshot();
            $invoice->save();

            $invoice->logHistory(InvoiceHistory::ACTION_ISSUED);
        });
    }

    private function generateInvoicePdf(Invoice $invoice): string
    {
        $host = request()->getHost();

        // Tenant-specific legacy views - kept for backward compatibility
        $documentCountry = $invoice->company_snapshot['country_code'] ?? $this->tenantContext->country();
        if ($documentCountry === 'ES' && $host === 'client1s.fakturalista.test') {
            return Pdf::loadView('invoices.yassine', ['invoice' => $invoice])->setPaper('a4')->output();
        }
        if ($documentCountry === 'ES' && $host === 'tachua.fakturalista.com') {
            return Pdf::loadView('invoices.tachua', ['invoice' => $invoice])->setPaper('a4')->output();
        }

        return $this->renderer->render($invoice, 'invoice');
    }

    /**
     * Normalize a phone number to the digits-only international format wa.me expects.
     *
     * +34 600 123 456  →  34600123456   (full international - correct)
     * 0034600123456    →  34600123456   (old 00-prefix - correct)
     * 600 123 456      →  600123456     (no country code - will likely mis-route on wa.me)
     *
     * This method never adds a country code; it only strips non-digits and common prefixes.
     * Store phone numbers in +<country><subscriber> format (e.g. +34600123456) for
     * reliable WhatsApp delivery.
     */
    private function normalizeWhatsAppNumber(string $phone): string
    {
        $phone = trim($phone);

        // Strip leading + (international dial prefix sign)
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        // Strip leading 00 (old-style international dialing code)
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        // Remove all remaining non-digit characters (spaces, dashes, parentheses, dots)
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * @param array<int, array<string, mixed>> $carts
     * @param DocumentCalculationResult $calculation must have been computed
     *        from these exact same $carts, in the same order - see
     *        buildCalculationLines().
     */
    private function syncCarts(int $invoiceId, array $carts, DocumentCalculationResult $calculation): void
    {
        Cart::where('cartable_type', 'App\Models\Invoice')
            ->where('cartable_id', $invoiceId)
            ->delete();

        foreach (array_values($carts) as $index => $cart) {
            Cart::create([
                'cartable_type' => 'App\Models\Invoice',
                'cartable_id'   => $invoiceId,
                'item_id'       => $cart['item_id'] ?? null,
                'description'   => $cart['description'] ?? null,
                'qty'           => $cart['qty'] ?? 1,
                'price'         => $cart['price'] ?? 0,
                'unite'         => $cart['unite'] ?? 'pc',
                'discount'      => $cart['discount'] ?? 0,
                // Authoritative taxable base from the server-side
                // calculator (Morocco Phase 1C.1) - never the client's
                // own $cart['total'].
                'total'         => $calculation->lines[$index]['taxable_base'] ?? 0,
                'vta'           => $cart['vta'] ?? 0,
                // Morocco Phase 1C.2 - semantic label, not arithmetic;
                // see TaxTreatment. Defaults to 'taxable' via the DB
                // column default if the client omits it.
                'tax_treatment' => $cart['tax_treatment'] ?? TaxTreatment::TAXABLE,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $carts raw cart rows as
     *        submitted by the client (qty/price/discount/vta/tax_treatment).
     * @return array<int, array{quantity: mixed, unit_price: mixed, discount: mixed, tax_rate: mixed, treatment: mixed}>
     */
    private function buildCalculationLines(array $carts): array
    {
        return array_map(fn (array $cart) => [
            'quantity'   => $cart['qty'] ?? 0,
            'unit_price' => $cart['price'] ?? 0,
            'discount'   => $cart['discount'] ?? 0,
            'tax_rate'   => $cart['vta'] ?? 0,
            'treatment'  => $cart['tax_treatment'] ?? TaxTreatment::TAXABLE,
        ], array_values($carts));
    }

    /**
     * Replaces this invoice's generic tax breakdown (Morocco Phase 1C.1)
     * with the freshly-computed one. Only ever called from store()/
     * update()/duplicate() - i.e. only while the invoice is still a
     * draft, since update() already refuses to run at all once an
     * invoice is locked. That's what makes an issued invoice's breakdown
     * immutable: nothing calls this for one, ever again.
     */
    private function persistTaxBreakdown(Invoice $invoice, DocumentCalculationResult $calculation): void
    {
        InvoiceTaxLine::where('invoice_id', $invoice->id)->delete();

        foreach ($calculation->taxBreakdown as $row) {
            InvoiceTaxLine::create([
                'invoice_id'   => $invoice->id,
                'rate'         => $row['rate'],
                'treatment'    => $row['treatment'],
                'taxable_base' => $row['taxable_base'],
                'tax_amount'   => $row['tax_amount'],
            ]);
        }
    }

    /**
     * The authoritative figures this invoice was just (re)computed with -
     * included in store()/update()'s JSON response so a caller can show
     * them without waiting for a separate show()/edit() round-trip. The
     * existing Vue forms don't consume this yet (no UI redesign in this
     * phase), but the values are correct and available from here on.
     */
    private function calculationResponse(Invoice $invoice, DocumentCalculationResult $calculation): array
    {
        return [
            'uuid'             => $invoice->uuid,
            'sub_total'        => $invoice->sub_total,
            'discount_amount'  => $invoice->discount_amount,
            'vta'              => $invoice->vta,
            'vta4'             => $invoice->vta4,
            'vta10'            => $invoice->vta10,
            'vta21'            => $invoice->vta21,
            'total'            => $invoice->total,
            'tax_breakdown'    => $calculation->taxBreakdown,
        ];
    }
}
