<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Portal Step 6A.1 - central map "connected Stripe account -> tenant".
 *
 * Connect events that carry no metadata of ours (account.updated,
 * deauthorization) only identify the connected account (the signed
 * event's `account`). The account id itself lives in each tenant's own
 * CompanyProfile, so without this table the central webhook could only
 * find the tenant by scanning every tenant database. Written only by
 * StripeConnectService when an account is linked, refreshed or
 * disconnected - never from webhook input.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_connect_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_account_id')->unique();
            $table->string('tenant_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_connect_accounts');
    }
};
