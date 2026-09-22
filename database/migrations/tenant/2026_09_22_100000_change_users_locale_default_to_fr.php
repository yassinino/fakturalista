<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fakturalista is Morocco-first; French is the application's default/
 * fallback locale everywhere else (config/app.php 'locale'/'fallback_locale',
 * both already 'fr', and TenantContextService::DEFAULT_LOCALE). The
 * `users.locale` column's own schema default was still the pre-Morocco
 * 'es' (2025_12_20_090000_add_locale_to_users_table.php) - a landmine for
 * any future code path that creates a User without explicitly setting
 * locale (today, TenantProvisioningService::provision() always does, but
 * the column default should not silently disagree with the app's own
 * documented default).
 *
 * Only changes the DEFAULT for future inserts - no existing row's stored
 * locale value is touched, no backfill. Uses a raw ALTER rather than
 * Blueprint::change() to avoid adding a doctrine/dbal dependency for a
 * single-column default change.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `locale` VARCHAR(10) NOT NULL DEFAULT 'fr'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `locale` VARCHAR(10) NOT NULL DEFAULT 'es'");
    }
};
