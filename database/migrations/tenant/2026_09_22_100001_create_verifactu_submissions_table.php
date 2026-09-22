<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Network/submission state, deliberately separate from the immutable
 * verifactu_records - a submission can be retried, its status can
 * change, none of that may ever touch the fiscal snapshot. See
 * docs/verifactu-aeat-connectivity.md §5/§6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifactu_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verifactu_record_id');
            $table->string('nif', 20);
            $table->string('environment', 10)->default('test');

            // The exact VerifactuXmlBuilder output, generated once at
            // submission-creation time from the immutable VerifactuRecord
            // snapshot - never regenerated. See §6 for why the full SOAP
            // envelope isn't separately persisted.
            $table->longText('payload_xml');
            $table->string('payload_checksum', 64);

            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();

            // AEAT's own literal terminology (RespuestaSuministro.xsd
            // lista L18/L19) - kept alongside the mapped `status` above,
            // not replaced by it.
            $table->string('aeat_estado_envio', 30)->nullable();
            $table->string('aeat_estado_registro', 30)->nullable();
            $table->unsignedInteger('aeat_error_code')->nullable();
            $table->string('aeat_error_description', 1500)->nullable();
            $table->string('csv')->nullable();
            $table->string('duplicate_of_id_peticion', 20)->nullable();

            $table->longText('response_raw')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('last_error', 1000)->nullable();

            $table->timestamps();

            $table->foreign('verifactu_record_id')->references('id')->on('verifactu_records')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_submissions');
    }
};
