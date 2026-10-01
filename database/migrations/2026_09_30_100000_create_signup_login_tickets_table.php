<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time "you just signed up, you're logged in" tickets.
 *
 * Self-service registration happens on the central domain, but the app
 * (and its Passport tokens) live on the new tenant's own subdomain, so a
 * session can't simply be carried over. Registration mints a short-lived,
 * single-use ticket bound to that tenant + user; the tenant's login page
 * exchanges it once for a normal Passport token (AuthController).
 *
 * Only the SHA-256 of the ticket is stored. Central (not tenant-side, not
 * cache) so creation outside tenancy and redemption inside it read the
 * same row regardless of cache driver/tenancy cache tagging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_login_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_login_tickets');
    }
};
