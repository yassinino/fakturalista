<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the existing billing-reminder dedup log (see
     * 2026_09_24_000001/000002) to also cover per-invoice due/overdue
     * reminders (Settings > Notifications), instead of a second log
     * table. `invoice_uuid` stays null for the pre-existing trial_ and
     * subscription_ reminder rows; `period_ends_at` is reused to hold the
     * invoice's due date for the new invoice_due_ and invoice_overdue_
     * reminder types - it already means "the date this reminder cycle is
     * keyed off", which a due date is just as much as a billing period end.
     *
     * If an invoice's due date changes, a reminder already sent for the
     * old due date has a different period_ends_at, so the new date is
     * free to trigger the same reminder_type again - matching the
     * existing trial/subscription "new cycle -> new dedup key" behavior.
     */
    public function up(): void
    {
        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->string('invoice_uuid')->nullable()->after('tenant_id');
        });

        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->dropUnique('tenant_reminder_logs_tenant_type_period_unique');
            $table->unique(
                ['tenant_id', 'reminder_type', 'invoice_uuid', 'period_ends_at'],
                'tenant_reminder_logs_tenant_type_invoice_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->dropUnique('tenant_reminder_logs_tenant_type_invoice_period_unique');
            $table->unique(
                ['tenant_id', 'reminder_type', 'period_ends_at'],
                'tenant_reminder_logs_tenant_type_period_unique'
            );
        });

        Schema::table('tenant_reminder_logs', function (Blueprint $table) {
            $table->dropColumn('invoice_uuid');
        });
    }
};
