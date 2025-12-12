<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
         DB::statement("
            CREATE TABLE `tenants` (
                `id` varchar(255) NOT NULL,
                `data` json NULL,
                `created_at` timestamp NULL,
                `updated_at` timestamp NULL,
                PRIMARY KEY (`id`)
            ) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
