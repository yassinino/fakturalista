<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            // Actions: created, issued, duplicated, paid, cancelled
            $table->string('action');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_history');
    }
};
