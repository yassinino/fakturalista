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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBiginteger('customer_id');
            $table->string('reference')->nullable();;
            $table->string('uuid')->unique();
            $table->date('date');
            $table->string('status')->nullable();
            $table->date('expiration_date');
            $table->string('payment_terms')->nullable();
            $table->double('sub_total')->nullable();
            $table->double('discount_rate')->nullable();
            $table->double('discount_amount')->nullable();
            $table->double('vta')->nullable();
            $table->double('total')->nullable();
            $table->text('note')->nullable();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')
            ->on('customers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
