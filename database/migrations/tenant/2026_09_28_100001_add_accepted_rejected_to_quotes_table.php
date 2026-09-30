<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Portal Step 4 - quote accept/reject. No equivalent columns
 * existed (Quote had no per-action timestamp at all, only the generic
 * created_at/updated_at), so these are the minimum needed to record
 * *when* a customer acted - no signature, no reason, no other personal
 * data. `status` itself already accepts any free-text string (see the
 * original quotes table migration - it's a plain VARCHAR, not an ENUM),
 * so the new "accepted"/"rejected" status values need no schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'rejected_at']);
        });
    }
};
