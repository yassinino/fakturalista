<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            // Rename current ID to something else
            if (Schema::hasColumn('oauth_access_tokens', 'id')) {
                $table->renameColumn('id', 'auto_id');
            }

            // Add a new string token ID (Passport expects this)
            if (!Schema::hasColumn('oauth_access_tokens', 'token_id')) {
                $table->string('token_id', 100)->unique()->after('auto_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('oauth_access_tokens', 'token_id')) {
                $table->dropColumn('token_id');
            }
            if (Schema::hasColumn('oauth_access_tokens', 'auto_id')) {
                $table->renameColumn('auto_id', 'id');
            }
        });
    }
};