<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy `plans.features` JSON column (free-text feature list from
 * before the plan_features pivot/Feature model existed) shadows the
 * features() BelongsToMany relationship on every query: Eloquent resolves
 * a real loaded attribute before ever falling through to a relation of
 * the same name, so $plan->features always returned this raw column
 * (null for every active plan) instead of the eager-loaded Feature
 * Collection - Plan::hasFeature() and any "enabled features" display were
 * silently always empty. Only the three inactive legacy plans (basico,
 * profesional, empresa) still hold data here; nothing reads this column
 * directly (confirmed: only $plan->features usages are relation-shaped).
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('plans', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('plans', function (Blueprint $table) {
            $table->json('features')->nullable();
        });
    }
};
