<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            // null  = onboarding wizard not yet submitted
            // value = timestamp when the client completed setup for the first time
            $table->timestamp('onboarding_completed_at')->nullable()->after('swift');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
