<?php

namespace App\Http\Controllers;

use App\Mail\QuoteEmail;
use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Quote;
use App\Http\Requests\QuoteRequest;
use App\Services\QuotePdfService;
use App\Services\QuoteToInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuoteController extends Controller
{
    public function index(): JsonResponse
    {
        $quotes = Quote::with('customer', 'carts')->orderBy('created_at', 'desc')->get();

        $company     = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        $quotes = $quotes->map(fn ($quote) => [
            'checked'         => false,
            'uuid'            => $quote->uuid,
            'customer'        => $quote->customer?->name,
            'customer_email'  => $quote->customer?->email,
            'customer_phone'  => $quote->customer?->phone,
            'company_name'    => $quote->customer?->company_name ?? '',
            'reference'       => $quote->reference,
            'date'            => $quote->date,
            'expiration_date' => $quote->expiration_date,
            'status'          => $quote->status,
            'total'           => $quote->total,
            'sub_total'       => $quote->sub_total,
            'vta'             => $quote->vta,
        ]);

        return response()->json([
            'quotes'       => $quotes,
            'company_name' => $companyName,
        ], 200);
    }

    public function create() {}

    public function store(QuoteRequest $request): JsonResponse
    {
        $customer  = Customer::where('uuid', $request->customer_id)->firstOrFail();
        $last      = Quote::orderBy('id', 'desc')->lockForUpdate()->first();
        $n         = isset($last) ? $last->id + 1 : 1;

        $new_quote = Quote::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'QUO-' . $n,
            'customer_id'     => $customer->id,
            'date'            => $request->date,
            'status'          => $request->status,
            'expiration_date' => $request->expiration_date,
            'payment_terms'   => $request->payment_terms,
            'sub_total'       => $request->sub_total,
            'discount_rate'   => $request->discount_rate,
            'discount_amount' => $request->discount_amount,
            'vta'             => $request->vta,
            'total'           => $request->total,
            'note'            => $request->note,
        ]);

        if (count($request->carts ?? []) > 0) {
            foreach ($request->carts as $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Quote',
                    'cartable_id'   => $new_quote->id,
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

        return response()->json(['message' => 'Quote created successfully.'], 200);
    }

    public function show(Quote $quote): JsonResponse
    {
        return response()->json(['quote' => $quote->load('customer', 'carts')]);
    }

    public function edit(Quote $quote): JsonResponse
    {
        $customer = Customer::withTrashed()->where('id', $quote->customer_id)->firstOrFail();

        $company     = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        return response()->json([
            'quote' => [
                'id'              => $quote->id,
                'uuid'            => $quote->uuid,
                'reference'       => $quote->reference,
                'customer_id'     => $customer->uuid,
                'customer_name'   => $customer->name,
                'customer_email'  => $customer->email,
                'customer_phone'  => $customer->phone,
                'company_name'    => $companyName,
                'address'         => $customer->address_billing ?? $customer->address ?? '',
                'date'            => $quote->date,
                'status'          => $quote->status,
                'expiration_date' => $quote->expiration_date,
                'payment_terms'   => $quote->payment_terms,
                'sub_total'       => $quote->sub_total,
                'discount_rate'   => $quote->discount_rate,
                'discount_amount' => $quote->discount_amount,
                'vta'             => $quote->vta,
                'total'           => $quote->total,
                'note'            => $quote->note,
                'carts'           => $quote->carts,
            ],
        ], 200);
    }

    public function update(QuoteRequest $request, Quote $quote): JsonResponse
    {
        $customer = Customer::where('uuid', $request->customer_id)->firstOrFail();

        Quote::where('id', $quote->id)->update([
            'customer_id'     => $customer->id,
            'date'            => $request->date,
            'status'          => $request->status,
            'expiration_date' => $request->expiration_date,
            'payment_terms'   => $request->payment_terms,
            'sub_total'       => $request->sub_total,
            'discount_rate'   => $request->discount_rate,
            'discount_amount' => $request->discount_amount,
            'vta'             => $request->vta,
            'total'           => $request->total,
            'note'            => $request->note,
        ]);

        if (count($request->carts ?? []) > 0) {
            Cart::where('cartable_type', 'App\Models\Quote')
                ->where('cartable_id', $quote->id)
                ->delete();

            foreach ($request->carts as $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Quote',
                    'cartable_id'   => $quote->id,
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

        return response()->json(['message' => 'Quote updated successfully.'], 200);
    }

    public function destroy(Quote $quote): JsonResponse
    {
        $quote->delete();
        return response()->json(['message' => 'Quote deleted successfully.'], 200);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $count = Quote::whereIn('uuid', $validated['ids'])->delete();

        return response()->json(['message' => "$count quote(s) deleted."]);
    }

    // ── Duplicate quote ────────────────────────────────────

    public function duplicate(Quote $quote): JsonResponse
    {
        $quote->load('carts');

        $newQuote = DB::transaction(function () use ($quote) {
            $last = Quote::lockForUpdate()->orderBy('id', 'desc')->first();
            $n    = isset($last) ? $last->id + 1 : 1;

            $copy = Quote::create([
                'uuid'            => Str::uuid()->toString(),
                'reference'       => 'QUO-' . $n,
                'customer_id'     => $quote->customer_id,
                'date'            => now()->toDateString(),
                'status'          => 'draft',
                'expiration_date' => now()->addDays(30)->toDateString(),
                'payment_terms'   => $quote->payment_terms,
                'sub_total'       => $quote->sub_total,
                'discount_rate'   => $quote->discount_rate,
                'discount_amount' => $quote->discount_amount,
                'vta'             => $quote->vta,
                'total'           => $quote->total,
                'note'            => $quote->note,
            ]);

            foreach ($quote->carts as $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Quote',
                    'cartable_id'   => $copy->id,
                    'item_id'       => $cart->item_id,
                    'description'   => $cart->description,
                    'qty'           => $cart->qty,
                    'price'         => $cart->price,
                    'unite'         => $cart->unite ?? 'pc',
                    'discount'      => $cart->discount,
                    'total'         => $cart->total,
                    'vta'           => $cart->vta,
                ]);
            }

            return $copy;
        });

        return response()->json([
            'message'    => 'Quote duplicated successfully.',
            'quote_uuid' => $newQuote->uuid,
        ], 200);
    }

    // ── Cancel quote ───────────────────────────────────────

    public function cancel(Quote $quote): JsonResponse
    {
        if ($quote->status === Quote::STATUS_CONVERTED) {
            return response()->json(['message' => 'Cannot cancel a converted quote.'], 422);
        }

        $quote->status = 'cancelled';
        $quote->save();

        return response()->json([
            'message' => 'Quote cancelled.',
            'status'  => $quote->status,
        ], 200);
    }

    // ── Convert quote → invoice ────────────────────────────

    public function convert(Quote $quote, QuoteToInvoiceService $service): JsonResponse
    {
        try {
            $invoice = $service->convert($quote);

            return response()->json([
                'success'      => true,
                'message'      => 'Quote converted successfully',
                'invoice_uuid' => $invoice->uuid,
            ], 200);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Quote conversion failed', [
                'quote_uuid' => $quote->uuid,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conversion failed',
            ], 500);
        }
    }

    // ── Send quote by email ────────────────────────────────

    public function send(Quote $quote, QuotePdfService $pdfService): JsonResponse
    {
        $quote->load('customer', 'carts');
        $customer = $quote->customer;

        if ($quote->status === Quote::STATUS_CONVERTED) {
            return response()->json(['message' => 'Cannot send a converted quote.'], 422);
        }

        if (empty($customer?->email)) {
            return response()->json([
                'message' => "Ce client n'a pas d'adresse e-mail. Ajoutez-en une sur sa fiche.",
            ], 422);
        }

        $company     = CompanyProfile::first();
        $companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));

        try {
            $pdfContent = $pdfService->generate($quote);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => "Unable to generate Quote PDF. Please try again.",
            ], 500);
        }

        $customMessage = request()->input('custom_message') ?: null;

        try {
            Mail::to($customer->email, $customer->name)->send(new QuoteEmail(
                quote:         $quote,
                customerName:  $customer->name,
                companyName:   $companyName,
                companyEmail:  $company?->email,
                companyPhone:  $company?->phone,
                pdfContent:    $pdfContent,
                customMessage: $customMessage,
            ));
        } catch (\Exception $e) {
            Log::error('Quote email delivery failed', [
                'quote_uuid' => $quote->uuid,
                'to'         => $customer->email,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'message' => "Le devis a été préparé, mais l'e-mail n'a pas pu être envoyé. Réessayez.",
            ], 500);
        }

        $quote->status = 'sent';
        $quote->save();

        return response()->json([
            'message' => "Devis envoyé à {$customer->email}.",
            'status'  => $quote->status,
            'sent_to' => $customer->email,
        ], 200);
    }

    // ── Stream Quote PDF download ──────────────────────────

    public function downloadPdf(Quote $quote, QuotePdfService $pdfService): Response
    {
        $quote->load('customer', 'carts');

        try {
            $pdfContent = $pdfService->generate($quote);
        } catch (\Throwable $e) {
            abort(500, 'Unable to generate Quote PDF.');
        }

        $filename = 'quote-' . $quote->reference . '.pdf';

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($pdfContent),
        ]);
    }
}
