<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings > Notifications. One JSON blob on the tenant's single
     * CompanyProfile row (same "existing settings mechanism" every other
     * settings section already uses) rather than a new table - see
     * App\Services\NotificationPreferencesService for the defaults every
     * existing tenant (null column) resolves to.
     */
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
