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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('contactable_type');
            $table->integer('contactable_id');
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->unsignedBiginteger('country_id')->unsigned()->nullable();
            $table->string('email')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('cell_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('post_code')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('country_id')->references('id')
            ->on('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
