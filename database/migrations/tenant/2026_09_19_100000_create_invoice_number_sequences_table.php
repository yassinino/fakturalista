<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concurrency-safe counters backing legal invoice numbering.
 *
 * One row per numbering series (e.g. "invoice-draft", "invoice-default",
 * "invoice-rectification"). InvoiceNumberingService reserves a number by
 * locking the row (SELECT ... FOR UPDATE) and incrementing it inside a
 * transaction, so a number, once handed out, is never handed out again -
 * unlike the previous MAX(id)+1 approach, which could reissue a number
 * if the invoice holding the current-highest id was soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series_key')->unique();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};
