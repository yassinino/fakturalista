<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subscription reminders (subscription_3_days/subscription_1_day) must
     * be dedup'd per billing cycle, not per tenant forever - a tenant who
     * gets a subscription_3_days reminder, then renews, must be able to
     * receive it again for the NEXT cycle's expiration. Trial reminders
     * (trial_3_days/trial_1_day) keep the old "once per tenant, ever"
     * behavior - a trial has no cycle, so their rows just leave this
     * column null.
     *
     * Note: a unique index never enforces uniqueness across NULL values in
     * MySQL (each NULL is distinct from every other NULL), so this column
     * being null for trial rows means the DB-level unique constraint alone
     * no longer guarantees "only once" for trial reminders - the
     * application-level exists() check in SendBillingRemindersCommand
     * (synchronous, single daily cron run) is what actually enforces that,
     * same as before this migration.
     */
    public function up(): void
    {
        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->timestamp('period_ends_at')->nullable()->after('reminder_type');
        });

        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'reminder_type']);
            $table->unique(['tenant_id', 'reminder_type', 'period_ends_at'], 'tenant_reminder_logs_tenant_type_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->dropUnique('tenant_reminder_logs_tenant_type_period_unique');
            $table->unique(['tenant_id', 'reminder_type']);
        });

        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->dropColumn('period_ends_at');
        });
    }
};
