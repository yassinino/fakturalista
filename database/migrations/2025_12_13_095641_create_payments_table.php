<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Multi-tenant: même tenant_id que ta table subscriptions
            $table->string('tenant_id', 36);
            // Lien optionnel vers la subscription centrale
            $table->unsignedBigInteger('subscription_id')->nullable();
            // D’où vient le paiement
            $table->string('provider');               // ex: 'stripe', 'paypal'
            $table->string('provider_payment_id');    // ex: id de l'invoice Stripe ou capture PayPal
            // Montant
            $table->decimal('amount', 10, 2);         // 29.00, 9.99, etc.
            $table->string('currency', 3)->default('EUR'); // 'EUR', 'USD', ...

            // Statut du paiement
            $table->string('status')->default('pending'); // 'paid', 'refunded', 'failed', ...

            // Infos utiles pour abonnement
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('billing_period_start')->nullable();
            $table->timestamp('billing_period_end')->nullable();

            $table->timestamps();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
