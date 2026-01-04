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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->double('amount')->default(0);
            $table->string('currency')->default('eur');
            $table->string('interval')->default('month'); // month|year
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            // 👇 Ajout des deux providers
            $table->string('stripe_price_id')->nullable(); // pour Stripe
            $table->string('paypal_plan_id')->nullable(); // pour PayPal
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
