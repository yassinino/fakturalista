<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Customer;
use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Http\Requests\InvoiceRequest;
use App\Mail\InvoiceEmail;
use App\Services\Pdf\TemplateRendererService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Storage;

class InvoiceController extends Controller
{
    public function __construct(private TemplateRendererService $renderer) {}

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
        $customer = Customer::where('uuid', $request->customer_id)->firstOrFail();

        $invoice = DB::transaction(function () use ($request, $customer) {
            $invoice = Invoice::create([
                'uuid'            => Str::uuid()->toString(),
                'reference'       => $this->nextReference(),
                'customer_id'     => $customer->id,
                'date'            => $request->date,
                'status'          => Invoice::STATUS_DRAFT,
                'expiration_date' => date('Y-m-d', strtotime($request->expiration_date)),
                'payment_terms'   => $request->payment_terms,
                'sub_total'       => $request->sub_total,
                'discount_rate'   => $request->discount_rate,
                'discount_amount' => $request->discount_amount,
                'vta'             => $request->vta,
                'vta4'            => $request->vta4,
                'vta10'           => $request->vta10,
                'vta21'           => $request->vta21,
                'total'           => $request->total,
                'note'            => $request->note,
            ]);

            $this->syncCarts($invoice->id, $request->carts ?? []);
            $invoice->logHistory(InvoiceHistory::ACTION_CREATED);

            return $invoice;
        });

        return response()->json(['message' => '¡Factura añadida!'], 200);
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
                'message' => 'Esta factura ya ha sido emitida y no se puede modificar. Usa "Duplicar" para crear una versión editable.',
            ], 403);
        }

        $customer = Customer::where('uuid', $request->customer_id)->firstOrFail();

        Invoice::where('id', $invoice->id)->update([
            'customer_id'     => $customer->id,
            'date'            => $request->date,
            'expiration_date' => $request->expiration_date,
            'payment_terms'   => $request->payment_terms,
            'sub_total'       => $request->sub_total,
            'discount_rate'   => $request->discount_rate,
            'discount_amount' => $request->discount_amount,
            'vta'             => $request->vta,
            'vta4'            => $request->vta4,
            'vta10'           => $request->vta10,
            'vta21'           => $request->vta21,
            'total'           => $request->total,
            'note'            => $request->note,
        ]);

        $this->syncCarts($invoice->id, $request->carts ?? []);

        return response()->json(['message' => '¡Factura actualizada!'], 200);
    }

    // ── Lifecycle Actions ──────────────────────────────────

    public function issue(Invoice $invoice): JsonResponse
    {
        if (!$invoice->isDraft()) {
            return response()->json([
                'message' => "No se puede emitir: la factura ya tiene el estado '{$invoice->status}'.",
            ], 422);
        }

        $invoice->status    = Invoice::STATUS_ISSUED;
        $invoice->issued_at = now();
        $invoice->save();

        $invoice->logHistory(InvoiceHistory::ACTION_ISSUED);

        return response()->json([
            'message'   => '¡Factura emitida!',
            'reference' => $invoice->reference,
            'status'    => $invoice->status,
            'issued_at' => $invoice->issued_at,
        ], 200);
    }

    public function markPaid(Invoice $invoice): JsonResponse
    {
        if (!$invoice->isIssued()) {
            return response()->json([
                'message' => "Solo se pueden marcar como pagadas las facturas emitidas (estado actual: '{$invoice->status}').",
            ], 422);
        }

        $invoice->status = Invoice::STATUS_PAID;
        $invoice->save();

        $invoice->logHistory(InvoiceHistory::ACTION_PAID);

        return response()->json(['message' => '¡Factura marcada como pagada!', 'status' => $invoice->status], 200);
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        if ($invoice->isCancelled()) {
            return response()->json(['message' => 'La factura ya está cancelada.'], 422);
        }

        $invoice->status = Invoice::STATUS_CANCELLED;
        $invoice->save();

        $invoice->logHistory(InvoiceHistory::ACTION_CANCELLED);

        return response()->json(['message' => '¡Factura cancelada!', 'status' => $invoice->status], 200);
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

        $duplicate = DB::transaction(function () use ($sourceInvoice) {
            $duplicate = Invoice::create([
                'uuid'              => Str::uuid()->toString(),
                'reference'         => $this->nextReference(),
                'customer_id'       => $sourceInvoice->customer_id,
                'date'              => now()->toDateString(),
                'status'            => Invoice::STATUS_DRAFT,
                'expiration_date'   => now()->addDays(30)->toDateString(),
                'payment_terms'     => $sourceInvoice->payment_terms,
                'sub_total'         => $sourceInvoice->sub_total,
                'discount_rate'     => $sourceInvoice->discount_rate,
                'discount_amount'   => $sourceInvoice->discount_amount,
                'vta'               => $sourceInvoice->vta,
                'vta4'              => $sourceInvoice->vta4,
                'vta10'             => $sourceInvoice->vta10,
                'vta21'             => $sourceInvoice->vta21,
                'total'             => $sourceInvoice->total,
                'note'              => $sourceInvoice->note,
                'source_invoice_id' => $sourceInvoice->id,
            ]);

            foreach ($sourceInvoice->carts as $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Invoice',
                    'cartable_id'   => $duplicate->id,
                    'item_id'       => $cart->item_id,
                    'description'   => $cart->description,
                    'qty'           => $cart->qty,
                    'price'         => $cart->price,
                    'unite'         => $cart->unite,
                    'discount'      => $cart->discount,
                    'total'         => $cart->total,
                    'vta'           => $cart->vta,
                ]);
            }

            $sourceInvoice->logHistory(InvoiceHistory::ACTION_DUPLICATED, [
                'duplicate_uuid' => $duplicate->uuid,
            ]);
            $duplicate->logHistory(InvoiceHistory::ACTION_CREATED, [
                'source_uuid' => $sourceInvoice->uuid,
            ]);

            return $duplicate;
        });

        return response()->json([
            'message'        => '¡Factura duplicada! Se ha creado un nuevo borrador.',
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
                'message' => "Impossible d'envoyer une facture annulée.",
            ], 422);
        }

        if (empty($customer?->email)) {
            return response()->json([
                'message' => "Ce client n'a pas d'adresse e-mail. Ajoutez-en une sur sa fiche avant d'envoyer.",
            ], 422);
        }

        // Auto-issue if still a draft
        if ($invoice->isDraft()) {
            $invoice->status    = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->save();
            $invoice->logHistory(InvoiceHistory::ACTION_ISSUED);
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
                'message' => "La facture a été émise, mais l'e-mail n'a pas pu être envoyé. Réessayez.",
                'status'  => $invoice->status,
            ], 500);
        }

        $sentAt = now();
        $invoice->logHistory(InvoiceHistory::ACTION_SENT, ['to' => $customer->email]);

        return response()->json([
            'message' => "Facture envoyée à {$customer->email}.",
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
     * code (e.g. 600 123 456) will be missing the prefix — wa.me may open
     * but target the wrong number.  Recommend storing phones as +<country><number>.
     */
    public function whatsapp(Invoice $invoice): JsonResponse
    {
        $invoice->load('customer', 'carts.product');
        $customer = $invoice->customer;

        if ($invoice->isCancelled()) {
            return response()->json([
                'message' => "Impossible de partager une facture annulée.",
            ], 422);
        }

        if (empty($customer?->phone)) {
            return response()->json([
                'message' => "Ce client n'a pas de numéro de téléphone. Ajoutez-en un sur sa fiche.",
            ], 422);
        }

        // Auto-issue draft so the PDF has a final reference number
        if ($invoice->isDraft()) {
            $invoice->status    = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->save();
            $invoice->logHistory(InvoiceHistory::ACTION_ISSUED);
        }

        // Generate and save the PDF so it has a stable public URL
        $pdfContent = $this->generateInvoicePdf($invoice);
        $filePath   = 'invoices/invoice_' . $invoice->uuid . '.pdf';
        Storage::disk('public')->put($filePath, $pdfContent);
        $pdfUrl = request()->getSchemeAndHttpHost() . Storage::url($filePath);

        $company     = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        $phone   = $this->normalizeWhatsAppNumber($customer->phone);
        $message = "Bonjour, veuillez trouver votre facture {$invoice->reference} de {$companyName} à ce lien : {$pdfUrl}. Merci !";

        // Append payment link if Stripe is configured
        if (!empty(config('services.stripe.secret'))) {
            $payLink  = request()->getSchemeAndHttpHost() . '/pay/' . $invoice->uuid;
            $message .= " 💳 Payer en ligne : {$payLink}";
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
            'message' => 'Factura imprimida!',
            'pdf_url' => Storage::url($filePath),
        ], 200);
    }

    // ── Delete ─────────────────────────────────────────────

    public function destroy(Invoice $invoice): JsonResponse
    {
        Invoice::where('uuid', $invoice->uuid)->delete();

        return response()->json(['message' => '¡Factura eliminada!'], 200);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $count = Invoice::whereIn('uuid', $validated['ids'])->delete();

        return response()->json(['message' => "$count invoice(s) deleted."]);
    }

    // ── Private helpers ────────────────────────────────────

    /**
     * Generate the next invoice reference using the existing numbering formula.
     *
     * Must be called inside a DB::transaction() so the FOR UPDATE lock is held
     * until the invoice row is actually written, preventing duplicate numbers
     * under concurrent requests.
     */
    private function generateInvoicePdf(Invoice $invoice): string
    {
        $host = request()->getHost();

        // Tenant-specific legacy views — kept for backward compatibility
        if ($host === 'client1s.fakturalista.test') {
            return Pdf::loadView('invoices.yassine', ['invoice' => $invoice])->setPaper('a4')->output();
        }
        if ($host === 'tachua.fakturalista.com') {
            return Pdf::loadView('invoices.tachua', ['invoice' => $invoice])->setPaper('a4')->output();
        }

        return $this->renderer->render($invoice, 'invoice');
    }

    /**
     * Normalize a phone number to the digits-only international format wa.me expects.
     *
     * +34 600 123 456  →  34600123456   (full international — correct)
     * 0034600123456    →  34600123456   (old 00-prefix — correct)
     * 600 123 456      →  600123456     (no country code — will likely mis-route on wa.me)
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

    private function nextReference(): string
    {
        $last = Invoice::lockForUpdate()->orderBy('id', 'desc')->first();
        $n    = isset($last) ? $last->id + 1 : 1;
        return 'INV-' . $n;
    }

    private function syncCarts(int $invoiceId, array $carts): void
    {
        Cart::where('cartable_type', 'App\Models\Invoice')
            ->where('cartable_id', $invoiceId)
            ->delete();

        foreach ($carts as $cart) {
            Cart::create([
                'cartable_type' => 'App\Models\Invoice',
                'cartable_id'   => $invoiceId,
                'item_id'       => $cart['item_id'] ?? null,
                'description'   => $cart['description'] ?? null,
                'qty'           => $cart['qty'] ?? 1,
                'price'         => $cart['price'] ?? 0,
                'unite'         => $cart['unite'] ?? 'pc',
                'discount'      => $cart['discount'] ?? 0,
                'total'         => $cart['total'] ?? 0,
                'vta'           => $cart['vta'] ?? 0,
            ]);
        }
    }
}
