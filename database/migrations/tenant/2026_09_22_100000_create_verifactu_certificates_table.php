<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One certificate per emitting NIF (mirrors verifactu_chain_states'
 * scoping - see docs/verifactu-aeat-connectivity.md §11). The PKCS#12
 * file itself is NOT stored here: only an encrypted-at-rest path on the
 * tenant's `local` (non-public) disk. The passphrase is a SEPARATE
 * secret (its own `encrypted` cast column) - key/password separation,
 * see §3 of the connectivity doc for the full security model and its
 * explicit limitations (this is not KMS/HSM-grade custody).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifactu_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('nif', 20)->unique();

            // Path on the `local` disk to the Crypt::encryptString()'d
            // PKCS#12 bytes - never the `public` disk, never web-served.
            $table->string('encrypted_file_path');
            // Crypt::encryptString()'d PKCS#12 import passphrase -
            // deliberately a separate column from the file itself.
            $table->text('passphrase');

            // Safe-to-display metadata, extracted once at upload time via
            // openssl_pkcs12_read()/openssl_x509_parse() - never the
            // private key or raw certificate bytes.
            $table->string('subject')->nullable();
            $table->string('issuer')->nullable();
            $table->string('serial_number')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();

            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_certificates');
    }
};
