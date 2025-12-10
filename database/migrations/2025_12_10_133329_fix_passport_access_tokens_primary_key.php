<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Renommer la colonne id -> auto_id (si elle existe)
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('oauth_access_tokens', 'id')
                && ! Schema::hasColumn('oauth_access_tokens', 'auto_id')) {
                // simple rename, on ne touche pas à la PK
                $table->renameColumn('id', 'auto_id');
            }
        });

        // 2) Ajouter la colonne token_id (string) SANS "after auto_id"
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('oauth_access_tokens', 'token_id')) {
                $table->string('token_id', 100)->unique()->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('oauth_access_tokens', 'token_id')) {
                $table->dropUnique('oauth_access_tokens_token_id_unique'); // nom par défaut
                $table->dropColumn('token_id');
            }
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('oauth_access_tokens', 'auto_id')
                && ! Schema::hasColumn('oauth_access_tokens', 'id')) {
                $table->renameColumn('auto_id', 'id');
            }
        });
    }
};