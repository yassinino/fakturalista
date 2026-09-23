<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RegisterTrialController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TenantNotFoundController;
use Illuminate\Support\Facades\Route;
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
    // Legacy manual free-trial request form - kept working (and its data,
    // if any, untouched) for historical reasons, but no longer linked to
    // from any public CTA - see the /register self-service flow below.
    Route::get('/free-trial', [HomeController::class, 'freeTrial'])->name('free-trial');
    Route::post('/free-trial', [HomeController::class, 'sendFreeTrial'])->name('free-trial.send');

    // Self-service trial signup - automatic tenant provisioning, replaces
    // the manual /free-trial workflow as the target of every public
    // "Empieza gratis" CTA. `throttle:register` is a dedicated, tighter
    // limiter (see RouteServiceProvider) - this endpoint creates a full
    // tenant database per successful submission, unlike ordinary form posts.
    Route::get('/register', [RegisterTrialController::class, 'create'])->name('register');
    Route::post('/register', [RegisterTrialController::class, 'store'])
        ->middleware('throttle:register')
        ->name('register.store');

    // Minimal "find my workspace" helper for the registration page's
    // "Already have an account? Sign in" link - see HomeController::login().
    Route::get('/login', [HomeController::class, 'login'])->name('login');
    Route::post('/login', [HomeController::class, 'findWorkspace'])
        ->middleware('throttle:login-finder')
        ->name('login.find-workspace');
    Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
    Route::post('/contact', [HomeController::class, 'sendContact'])
        ->middleware('throttle:contact')
        ->name('contact.send');
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [HomeController::class, 'about'])->name('about');
    Route::get('/faq', [HomeController::class, 'faq'])->name('faq');
    Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
    Route::get('/verifactu', [HomeController::class, 'verifactu'])->name('verifactu');
    Route::get('/security', [HomeController::class, 'security'])->name('security');
    Route::get('/integrations', [HomeController::class, 'integrations'])->name('integrations');
    Route::get('/documentation', [HomeController::class, 'documentation'])->name('documentation');
    Route::get('/help-center', [HomeController::class, 'helpCenter'])->name('help-center');
    Route::get('/api-docs', [HomeController::class, 'apiDocs'])->name('api-docs');
    Route::get('/changelog', [HomeController::class, 'changelog'])->name('changelog');
    Route::get('/legal-notice', [HomeController::class, 'legalNotice'])->name('legal-notice');
    Route::get('/privacy-policy', [HomeController::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms', [HomeController::class, 'terms'])->name('terms');
    Route::get('/cookie-policy', [HomeController::class, 'cookiePolicy'])->name('cookie-policy');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

    // Shown when $onFail redirects an unknown tenant subdomain to the central domain.
    Route::get('/tenant-not-found', [TenantNotFoundController::class, 'show'])->name('tenant.not-found');
};

// Development domain registered first so production domains win route() name resolution.
Route::domain('fakturalista.test')
    ->middleware('set.locale')
    ->group($siteRoutes);

Route::domain('fakturalista.com')
    ->middleware('set.locale')
    ->group($siteRoutes);

Route::domain('www.fakturalista.com')
    ->middleware('set.locale')
    ->group($siteRoutes);
