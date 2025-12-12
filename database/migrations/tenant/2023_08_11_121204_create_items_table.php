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
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBiginteger('family_id');
            $table->string('reference')->nullable();;
            $table->string('name');
            $table->tinyInteger('type');
            $table->double('sales_price')->nullable();;
            $table->double('purchase_price')->nullable();;
            $table->text('description')->nullable();;
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('family_id')->references('id')
            ->on('families')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
