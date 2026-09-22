<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records exactly which plan_prices row (market + interval + currency)
 * a subscription was actually created against - set once, from the
 * Checkout Session's own metadata, in
 * StripeWebhookController::handleCheckoutSessionCompleted().
 *
 * Without this, displaying "what am I actually paying" anywhere after
 * checkout (e.g. the success page) would have to re-guess the tenant's
 * current market instead of showing what Stripe actually charged.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_price_id')->nullable()->after('plan_id')
                ->constrained('plan_prices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_price_id');
        });
    }
};
