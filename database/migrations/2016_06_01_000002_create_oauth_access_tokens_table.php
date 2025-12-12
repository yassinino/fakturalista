<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE TABLE `oauth_access_tokens` (
                `id` varchar(100) NOT NULL,
                `user_id` bigint unsigned NULL,
                `client_id` bigint unsigned NOT NULL,
                `name` varchar(255) NULL,
                `scopes` text NULL,
                `revoked` tinyint(1) NOT NULL,
                `created_at` timestamp NULL,
                `updated_at` timestamp NULL,
                `expires_at` datetime NULL,
                PRIMARY KEY (`id`),
                KEY `oauth_access_tokens_user_id_index` (`user_id`),
                KEY `oauth_access_tokens_client_id_index` (`client_id`)
            ) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_access_tokens');
    }
};
