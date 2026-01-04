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
        Schema::create('billing_profiles', function (Blueprint $table) {
        $table->id();
        $table->uuid('tenant_id'); // FK to tenants.id (string/uuid)
        $table->string('provider'); // stripe | paypal
        $table->string('provider_customer_id')->nullable();
        $table->string('email')->nullable();
        $table->json('raw')->nullable(); // raw customer JSON
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};
