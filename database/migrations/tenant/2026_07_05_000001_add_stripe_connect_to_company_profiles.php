<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('swift');
            $table->string('stripe_connection_status')->nullable()->after('stripe_account_id'); // 'connected' | 'incomplete'
            $table->boolean('onboarding_completed')->default(false)->after('stripe_connection_status');
            $table->boolean('charges_enabled')->default(false)->after('onboarding_completed');
            $table->boolean('payouts_enabled')->default(false)->after('charges_enabled');
            $table->timestamp('stripe_connected_at')->nullable()->after('payouts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_account_id',
                'stripe_connection_status',
                'onboarding_completed',
                'charges_enabled',
                'payouts_enabled',
                'stripe_connected_at',
            ]);
        });
    }
};
