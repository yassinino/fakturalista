<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central feedback inbox (public site visitors + tenant app users), read
 * and answered from the Filament backoffice - see App\Models\Feedback.
 *
 * Central, not tenant-side: the administrator needs one list across every
 * tenant. tenant_id/user_id are always set server-side (tenancy context +
 * authenticated user), never from the request body. user_id points into
 * that tenant's own `users` table, hence no foreign key; company_name and
 * user_name are snapshots so the row stays readable if either goes away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 20)->default('new')->index();
            $table->string('source', 10)->index();
            $table->text('message');
            $table->string('email');
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('company_name')->nullable();
            $table->string('user_name')->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 50)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::create('feedback_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_replies');
        Schema::dropIfExists('feedback');
    }
};
