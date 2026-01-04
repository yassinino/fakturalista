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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            // Company identity
            $table->string('legal_name');                  // Company legal name
            $table->string('trade_name')->nullable();      // Brand name (optional)
            $table->string('industry')->nullable();

            // Tax / registration
            $table->string('country_code', 2)->default('ES'); // ISO2
            $table->string('tax_id')->nullable();          // NIF/CIF/ICE...
            $table->string('vat_number')->nullable();
            $table->string('registration_number')->nullable();

            // Contact
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable(); // free text label (optional)

            // Branding
            $table->string('logo_path')->nullable();       // storage path
            $table->string('stamp_path')->nullable();      // optional stamp/signature
            $table->string('brand_color', 16)->nullable(); // ex: #E91E63
            $table->string('invoice_footer_note', 1000)->nullable();

            // Invoice defaults
            $table->string('invoice_prefix', 20)->default('INV');
            $table->unsignedInteger('invoice_next_number')->default(1);
            $table->string('invoice_number_format', 60)->default('{PREFIX}-{YYYY}-{NUMBER}'); // customizable
            $table->string('timezone')->default('Europe/Madrid');
            $table->string('locale', 10)->default('es');   // UI/pdf language
            $table->string('currency', 3)->default('EUR');

            // Payment / bank (optional)
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
