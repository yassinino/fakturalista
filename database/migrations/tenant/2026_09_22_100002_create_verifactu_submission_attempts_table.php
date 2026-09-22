<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per actual network attempt against AEAT for a given
 * VerifactuSubmission - a submission can have several (retries), see
 * docs/verifactu-aeat-connectivity.md §10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifactu_submission_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verifactu_submission_id');
            $table->unsignedInteger('attempt_number');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // success | transport_error | tls_error | soap_fault | timeout
            $table->string('outcome', 30)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            // Sanitized only - never certificate/key/passphrase content.
            $table->string('error_message', 1000)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamps();

            $table->foreign('verifactu_submission_id', 'verifactu_submission_attempts_submission_foreign')
                ->references('id')->on('verifactu_submissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_submission_attempts');
    }
};
