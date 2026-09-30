<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Portal Step 6A - one row per Stripe Checkout Session created from
 * the portal for an invoice. Tenant-side (the invoice lives here) and
 * deliberately separate from the central `payments` table, which records
 * Fakturalista's own SaaS subscription payments.
 *
 * What we asked Stripe to charge (account, amount in minor units,
 * currency) is recorded BEFORE the customer pays, so the webhook can
 * verify a completed session against our own record instead of trusting
 * the event payload - see PortalInvoicePaymentService::handleSessionEvent().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('provider', 20)->default('stripe');
            $table->string('stripe_account_id');
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            // pending (before Stripe call) | open | paid | expired | failed | rejected
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason', 60)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_attempts');
    }
};
