<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orden HAC/1177/2024 art. 16.2 mandates a wait-time-between-submissions
 * flow-control mechanism per NIF, starting at 60s and updated by AEAT's
 * own TiempoEsperaEnvio in every response - see
 * docs/verifactu-aeat-connectivity.md §10. verifactu_chain_states is
 * already the per-NIF singleton (Phase 2B), so this is its natural home.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifactu_chain_states', function (Blueprint $table) {
            $table->timestamp('next_submission_not_before')->nullable()->after('last_verifactu_record_id');
        });
    }

    public function down(): void
    {
        Schema::table('verifactu_chain_states', function (Blueprint $table) {
            $table->dropColumn('next_submission_not_before');
        });
    }
};
