<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Plan (Starter/Pro/Business) row keeps its single set of
 * features/limits/marketing copy - country/currency-specific pricing lives
 * here instead, so a market can be added without duplicating the whole
 * plan (see the old, now-inactive Básico/Profesional/Empresa rows, which
 * duplicated entire plans per market and are exactly what this avoids).
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->char('currency', 3);
            $table->string('interval'); // 'monthly' | 'yearly'
            $table->unsignedInteger('amount'); // minor units (cents)
            // Real Stripe Price object id (test mode during development) -
            // never charge in a currency the UI doesn't also display.
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'country_code', 'interval']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('plan_prices');
    }
};
