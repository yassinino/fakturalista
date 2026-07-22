<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\SubscriptionController;
/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    Route::prefix('admin/subscription/checkout')->group(function () {
        Route::get('/success', [HomeController::class, 'success'])->name('admin.subscription.checkout.success');
        Route::get('/cancel', [HomeController::class, 'cancel'])->name('admin.subscription.checkout.cancel');
    });

    // Public client-facing payment pages (no auth required)
    Route::get('/pay/{uuid}',         [PaymentController::class, 'publicPayPage'])->name('invoice.pay');
    Route::get('/pay/{uuid}/success', [PaymentController::class, 'paymentSuccess'])->name('invoice.pay.success');
    Route::get('/pay/{uuid}/cancel',  [PaymentController::class, 'paymentCancel'])->name('invoice.pay.cancel');

    // Tenant-level Stripe webhook (invoice payment events)
    Route::post('/payment/webhook', [PaymentController::class, 'stripeWebhook'])
        ->name('invoice.payment.webhook')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    // Stripe Connect — OAuth redirect and callback (no auth guard; state param provides CSRF protection)
    Route::get('/settings/payments/stripe/connect',  [StripeConnectController::class, 'redirect'])
        ->name('stripe.connect');
    Route::get('/settings/payments/stripe/callback', [StripeConnectController::class, 'callback'])
        ->name('stripe.connect.callback');
    Route::post('/settings/payments/stripe/disconnect', [StripeConnectController::class, 'disconnect'])
        ->name('stripe.disconnect');

    // Stripe Connect webhook (platform-level Connect events from all connected accounts)
    // Register this URL in Stripe Dashboard → Webhooks as a "Connect webhook"
    Route::post('/connect/webhook', [StripeConnectController::class, 'handleWebhook'])
        ->name('stripe.connect.webhook')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    Route::get('/', function () {
        return redirect('/admin/login');
    });

    Route::get('/admin/{any}', function () {
        return view('app');
    })->where('any', '^(?!api).*$');
});
