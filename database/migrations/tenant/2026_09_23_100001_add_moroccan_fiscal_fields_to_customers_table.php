<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morocco Phase 1B (docs/morocco-phase-1b-identity.md). `customers.ice`
 * already existed; these two mirror the same pair just added to
 * `company_profiles`, for a business customer that wants to record its
 * own IF/RC (both fully optional - a customer is never required to have
 * any of ICE/IF/RC, see Customer::isBusiness()/isIndividual() and
 * CustomerRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('if_number')->nullable()->after('ice');
            $table->string('commercial_register')->nullable()->after('if_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['if_number', 'commercial_register']);
        });
    }
};
