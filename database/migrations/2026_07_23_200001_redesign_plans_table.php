<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        // 1. Add new structural columns
        Schema::connection('mysql')->table('plans', function (Blueprint $table) {
            $table->json('short_description')->nullable()->after('slug');
            $table->json('full_description')->nullable()->after('short_description');
            $table->json('badge')->nullable()->after('full_description');
            $table->json('button_text')->nullable()->after('badge');
            $table->string('button_url')->nullable()->after('button_text');
            $table->string('button_action')->default('checkout')->after('button_url');
            $table->string('icon')->nullable()->after('button_action');
            $table->string('color')->default('#fa7070')->after('icon');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('color');
            $table->boolean('is_featured')->default(false)->after('sort_order');
            $table->unsignedInteger('monthly_price')->default(0)->after('is_featured');
            $table->unsignedInteger('yearly_price')->nullable()->after('monthly_price');
            $table->unsignedSmallInteger('trial_days')->default(14)->after('yearly_price');
            $table->string('stripe_price_id_monthly')->nullable()->after('trial_days');
            $table->string('stripe_price_id_yearly')->nullable()->after('stripe_price_id_monthly');
            $table->json('name_i18n')->nullable()->after('stripe_price_id_yearly');
        });

        // 2. Migrate existing data
        DB::connection('mysql')->statement(
            "UPDATE plans SET
                name_i18n      = JSON_OBJECT('fr', IFNULL(name, ''), 'en', IFNULL(name, ''), 'es', IFNULL(name, '')),
                monthly_price  = CASE WHEN amount >= 100 THEN ROUND(amount) ELSE ROUND(amount * 100) END
            WHERE 1=1"
        );

        // 3. Replace name varchar with JSON
        DB::connection('mysql')->statement('ALTER TABLE plans DROP COLUMN name');
        DB::connection('mysql')->statement('ALTER TABLE plans ADD COLUMN name JSON NULL AFTER slug');
        DB::connection('mysql')->statement('UPDATE plans SET name = name_i18n');
        Schema::connection('mysql')->table('plans', fn (Blueprint $t) => $t->dropColumn('name_i18n'));
    }

    public function down(): void
    {
        // Restore name as varchar (best-effort, extracts 'en' or 'fr' value)
        DB::connection('mysql')->statement('ALTER TABLE plans ADD COLUMN name_old VARCHAR(255) NULL AFTER slug');
        DB::connection('mysql')->statement(
            "UPDATE plans SET name_old = COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')),
                JSON_UNQUOTE(JSON_EXTRACT(name, '$.fr')),
                ''
            )"
        );
        DB::connection('mysql')->statement('ALTER TABLE plans DROP COLUMN name');
        DB::connection('mysql')->statement('ALTER TABLE plans CHANGE COLUMN name_old name VARCHAR(255) NULL');

        Schema::connection('mysql')->table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'short_description', 'full_description', 'badge', 'button_text',
                'button_url', 'button_action', 'icon', 'color', 'sort_order',
                'is_featured', 'monthly_price', 'yearly_price', 'trial_days',
                'stripe_price_id_monthly', 'stripe_price_id_yearly',
            ]);
        });
    }
};
