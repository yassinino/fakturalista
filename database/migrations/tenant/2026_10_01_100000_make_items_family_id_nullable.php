<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product/Service category becomes optional: items.family_id may be NULL.
 *
 * Only the column's nullability changes - the existing foreign key to
 * `families` (and its ON DELETE behaviour) and every existing item's
 * category stay exactly as they are. Raw MODIFY because this Laravel 10 app
 * has no doctrine/dbal for ->change(); MODIFY keeps the FK in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE items MODIFY family_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Only reversible while every item still has a category.
        if (DB::getDriverName() === 'mysql' && !DB::table('items')->whereNull('family_id')->exists()) {
            DB::statement('ALTER TABLE items MODIFY family_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
