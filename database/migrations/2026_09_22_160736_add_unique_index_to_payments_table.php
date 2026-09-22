<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe redelivers webhooks (retries, and occasionally duplicate
 * deliveries of the same event). Without this constraint,
 * StripeWebhookController::handleInvoicePaymentSucceeded() could insert
 * the same Stripe invoice payment twice - this makes that impossible at
 * the DB level, not just by application-level care.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('payments', function (Blueprint $table) {
            $table->unique(['provider', 'provider_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_payment_id']);
        });
    }
};
