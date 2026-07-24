<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('plan_marketing_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('text_fr');
            $table->string('text_en')->nullable();
            $table->string('text_es')->nullable();
            $table->string('icon')->default('✓');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_highlighted')->default(false);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('plan_marketing_items');
    }
};
