<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->after('note');
            $table->text('stripe_payment_url')->nullable()->after('stripe_session_id');
            $table->timestamp('stripe_session_expires_at')->nullable()->after('stripe_payment_url');
            $table->timestamp('paid_at')->nullable()->after('stripe_session_expires_at');
            $table->string('paid_via', 50)->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_session_id',
                'stripe_payment_url',
                'stripe_session_expires_at',
                'paid_at',
                'paid_via',
            ]);
        });
    }
};
