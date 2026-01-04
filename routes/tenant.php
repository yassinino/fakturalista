<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\HomeController;
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

    Route::get('/', function () {
        return redirect('/admin/login');
    });

    Route::get('/admin/{any}', function () {
        return view('app');
    })->where('any', '^(?!api).*$');
});
