<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // This table lives in the CENTRAL database - never in tenant databases.
    // It records every request to an unknown tenant subdomain for conversion
    // tracking and link-rot analysis.

    public function up(): void
    {
        Schema::create('tenant_domain_visits', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 253)->index();       // full host: walid.fakturalista.com
            $table->string('subdomain', 63)->nullable();  // extracted prefix: walid
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domain_visits');
    }
};
