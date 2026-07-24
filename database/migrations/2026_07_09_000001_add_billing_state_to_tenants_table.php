<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Runs on the central (default) connection - NOT a tenant migration.
    // These two columns are the source of truth for paywall enforcement.
    // Checked BEFORE any tenant-DB query so the middleware never causes
    // a chicken-and-egg problem.
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // null       = account created, onboarding not yet done (trial not started)
            // 'trialing' = onboarding complete, within free-trial window
            // 'active'   = paid Stripe subscription currently active
            // 'expired'  = trial ended, no active subscription → read-only
            // 'canceled' = had subscription, it was canceled → read-only
            $table->string('subscription_status')->nullable()->after('data');

            // Set to now() + 3 months when onboarding is submitted.
            // null until onboarding completes.
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'trial_ends_at']);
        });
    }
};
