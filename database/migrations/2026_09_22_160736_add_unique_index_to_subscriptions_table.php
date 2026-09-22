<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Defense in depth alongside StripeWebhookController's own
 * Subscription::updateOrCreate() on this same key - a duplicate/concurrent
 * webhook delivery must not be able to create a second subscription row
 * for the same tenant+provider+Stripe subscription id.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('subscriptions', function (Blueprint $table) {
            $table->unique(['tenant_id', 'provider', 'provider_subscription_id'], 'subscriptions_tenant_provider_sub_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_tenant_provider_sub_unique');
        });
    }
};
