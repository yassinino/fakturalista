<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 6A.1 - the /pay/{uuid} link now uses the same payment-attempt
 * architecture as the Client Portal. `purpose` keeps the two apart so an
 * open session is only ever reused for the flow that created it (a portal
 * session's success URL carries the portal token and must never be handed
 * to a /pay visitor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payment_attempts', function (Blueprint $table) {
            $table->string('purpose', 40)->default('client_portal_invoice')->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payment_attempts', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
