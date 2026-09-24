<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedup log for the daily billing-reminder command (central connection,
     * alongside `subscriptions`/`tenants` - trial and subscription data are
     * both central, not per-tenant). One row per reminder actually sent;
     * the unique index is what stops a reminder from ever being sent twice.
     */
    public function up(): void
    {
        Schema::create('tenant_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('reminder_type'); // trial_3_days | trial_1_day | subscription_3_days | subscription_1_day
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'reminder_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_reminder_logs');
    }
};
