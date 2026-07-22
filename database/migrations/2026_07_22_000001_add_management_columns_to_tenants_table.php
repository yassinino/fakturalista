<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Runs on the central (default) connection — NOT a tenant migration.
    // These columns are managed by the Filament admin panel when provisioning
    // or editing a tenant. They are in getCustomColumns() so VirtualColumn
    // stores them as real DB columns (queryable / sortable) rather than JSON.
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('data');
            $table->string('owner_email')->nullable()->after('owner_name');
            $table->string('company_name')->nullable()->after('owner_email');
            $table->string('company_email')->nullable()->after('company_name');
            $table->string('company_phone', 50)->nullable()->after('company_email');
            $table->char('country', 2)->nullable()->after('company_phone');
            $table->string('timezone', 100)->nullable()->default('Europe/Madrid')->after('country');
            $table->char('currency', 3)->nullable()->default('EUR')->after('timezone');
            $table->char('language', 5)->nullable()->default('es')->after('currency');

            // Operational status — independent from billing subscription_status.
            // 'active'    = tenant can access the app normally.
            // 'suspended' = admin-blocked, regardless of billing state.
            $table->enum('status', ['active', 'suspended'])->default('active')->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'owner_name', 'owner_email',
                'company_name', 'company_email', 'company_phone',
                'country', 'timezone', 'currency', 'language',
                'status',
            ]);
        });
    }
};
