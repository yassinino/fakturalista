<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StripeWebhookController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

$siteRoutes = function () {
    Route::post('/locale', [HomeController::class, 'setLocale'])->name('locale.set');
    Route::get('/free-trial', [HomeController::class, 'freeTrial'])->name('free-trial');
    Route::post('/free-trial', [HomeController::class, 'sendFreeTrial'])->name('free-trial.send');
    Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
    Route::post('/contact', [HomeController::class, 'sendContact'])->name('contact.send');
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [HomeController::class, 'about'])->name('about');
    Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');

    // tu peux ajouter autant de pages que tu veux ici
};

Route::domain('fakturalista.com')
    ->middleware('set.locale')
    ->group($siteRoutes);
Route::domain('wwww.fakturalista.com')
    ->middleware('set.locale')
    ->group($siteRoutes);

Route::domain('fakturalista.test')
    ->middleware('set.locale')
    ->group($siteRoutes);
