<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            // Si la colonne 'id' n'existe plus (car on l'a renommée en auto_id)
            if (! Schema::hasColumn('oauth_access_tokens', 'id')) {
                // On ajoute 'id' en VARCHAR(100), comme Passport l'attend
                // On peut la rendre unique, ce qui est logique pour un token
                $table->string('id', 100)->unique()->after('auto_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('oauth_access_tokens', 'id')) {
                // Supprimer l'index unique si Laravel en a créé un avec ce nom
                $table->dropUnique('oauth_access_tokens_id_unique');
                $table->dropColumn('id');
            }
        });
    }
};