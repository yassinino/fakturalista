<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoicePaymentsController;
use App\Http\Controllers\InvoiceAiController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\OnboardingController;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
*/

// ── Public (no auth) ─────────────────────────────────────────────────────
Route::post('login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
Route::get('/countries', [CountryController::class, 'index']);

// ── Authenticated ─────────────────────────────────────────────────────────
Route::middleware(['auth:api', 'set.locale'])->group(function () {

    // Always-available regardless of onboarding/subscription state
    Route::get('/user',             [AuthController::class, 'user']);
    Route::put('/user',             [AuthController::class, 'update']);
    Route::post('/logout',          [AuthController::class, 'logout']);

    // Onboarding (bypasses RequireOnboarding and EnforceSubscription)
    Route::get('/onboarding',  [OnboardingController::class, 'show']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);

    // Subscription (bypasses both guards — needed to subscribe from expired state)
    Route::get('/subscription',              [SubscriptionController::class, 'index']);
    Route::post('/subscription/checkout',    [SubscriptionController::class, 'createCheckoutSession']);
    Route::post('/subscription/cancel',      [SubscriptionController::class, 'cancel']);
    Route::resource('/plans', PlanController::class);

    // ── All routes below require onboarding to be complete AND enforce read-only ──
    Route::middleware(['require.onboarding', 'enforce.subscription'])->group(function () {

        Route::get('/stats/counts',       [StatsController::class, 'counts']);
        Route::get('/stats/cash-overview',[StatsController::class, 'cashOverview']);

        // Settings (reads always work; writes allowed even in read-only — profile management)
        Route::get('/settings',                            [CompanyProfileController::class, 'show']);
        Route::match(['post', 'put'], '/settings',         [CompanyProfileController::class, 'update']);
        Route::get('/settings/payments/stripe/status',     [StripeConnectController::class, 'status']);
        Route::post('/settings/payments/stripe/disconnect', [StripeConnectController::class, 'disconnect']);

        // Items
        Route::post('/items/bulk-delete', [ItemController::class, 'bulkDelete']);
        Route::resource('/items', ItemController::class);
        Route::resource('/families', FamilyController::class);

        // Customers
        Route::post('/customers/bulk-delete', [CustomerController::class, 'bulkDelete']);
        Route::resource('/customers', CustomerController::class);

        // Quotes
        Route::post('/quotes/bulk-delete',       [QuoteController::class, 'bulkDelete']);
        Route::post('/quotes/{quote}/convert',   [QuoteController::class, 'convert']);
        Route::post('/quotes/{quote}/send',      [QuoteController::class, 'send']);
        Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate']);
        Route::post('/quotes/{quote}/cancel',    [QuoteController::class, 'cancel']);
        Route::get('/quotes/{quote}/pdf',        [QuoteController::class, 'downloadPdf']);
        Route::resource('/quotes', QuoteController::class);

        // Invoices
        Route::post('/invoices/bulk-delete',                [InvoiceController::class, 'bulkDelete']);
        Route::post('/invoices/{invoice}/issue',            [InvoiceController::class, 'issue']);
        Route::post('/invoices/{invoice}/mark-paid',        [InvoiceController::class, 'markPaid']);
        Route::post('/invoices/{invoice}/cancel',           [InvoiceController::class, 'cancel']);
        Route::post('/invoices/{invoice}/duplicate',        [InvoiceController::class, 'duplicate']);
        Route::post('/invoices/{invoice}/send',             [InvoiceController::class, 'send']);
        Route::post('/invoices/{invoice}/whatsapp',         [InvoiceController::class, 'whatsapp']);
        Route::post('/invoices/{invoice}/create-payment-link', [PaymentController::class, 'getOrCreatePaymentLink']);
        Route::get('/invoices/{invoice}/history',           [InvoiceController::class, 'history']);
        Route::resource('/invoices', InvoiceController::class);
        Route::post('/invoices/print', [InvoiceController::class, 'print_invoice']);

        // Templates
        Route::post('/invoice-templates/upload-logo', [InvoiceTemplateController::class, 'uploadLogo']);
        Route::resource('/invoice-templates', InvoiceTemplateController::class);

        // AI
        Route::post('/ai/parse-invoice', [InvoiceAiController::class, 'parseInvoice']);

        // Payments
        Route::get('/payments/summary',   [InvoicePaymentsController::class, 'summary']);
        Route::get('/payments/clients',   [InvoicePaymentsController::class, 'clients']);
        Route::get('/payments/payable',   [InvoicePaymentsController::class, 'payable']);
        Route::post('/payments/record',   [InvoicePaymentsController::class, 'record']);
        Route::put('/payments/{invoice}', [InvoicePaymentsController::class, 'update']);
        Route::get('/payments/{invoice}', [InvoicePaymentsController::class, 'show']);
        Route::get('/payments',           [InvoicePaymentsController::class, 'index']);
    });
});
