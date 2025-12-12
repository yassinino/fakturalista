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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('cartable_type');
            $table->integer('cartable_id');
            $table->text('description')->nullable();
            $table->double('qty')->nullable();
            $table->double('price')->nullable();
            $table->double('discount')->nullable();
            $table->double('total')->nullable();
            $table->double('vta')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
