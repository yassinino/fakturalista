<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Portal, Step 1 (backend foundation only - no frontend yet).
 *
 * One row per issued portal access token for a customer. The raw token
 * itself is never stored - only its SHA-256 hash (token_hash), looked up
 * by exact match; see App\Services\ClientPortal\ClientPortalService. A
 * customer can have more than one row over time (revoked ones kept for
 * audit/history via revoked_at), but the service only ever treats the
 * most recent non-revoked row as active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_portal_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // SHA-256 hex digest (64 chars) of the raw token - unique so a
            // lookup is a single indexed equality query, never a scan.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_portal_access');
    }
};
