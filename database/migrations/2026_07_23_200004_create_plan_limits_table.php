<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('plan_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('resource'); // 'invoices_per_month', 'customers', 'users', 'products', 'quotes'
            $table->unsignedInteger('value')->nullable(); // null = unlimited
            $table->timestamps();

            $table->unique(['plan_id', 'resource']);
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('plan_limits');
    }
};
