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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBiginteger('billing_country_id')->nullable()->constrained();
            $table->unsignedBiginteger('delivery_country_id')->nullable()->constrained();            
            $table->string('uuid')->unique();
            $table->string('reference')->nullable();
            $table->tinyInteger('type');
            $table->string('company_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('city_billing')->nullable();
            $table->text('address_billing')->nullable();
            $table->string('post_code_billing')->nullable();
            $table->tinyInteger('is_same_address')->nullable();
            $table->string('city_delivery')->nullable();
            $table->text('address_delivery')->nullable();
            $table->string('post_code_delivery')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // $table->foreign('billing_country_id')->references('id')
            // ->on('countries')->onDelete('cascade');
            // $table->foreign('delivery_country_id')->references('id')
            // ->on('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
