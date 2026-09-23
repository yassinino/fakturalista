<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Team/user management (Users page): every tenant user now has a role,
 * 'admin' or 'member'. Only admins can manage the team (see
 * UserController); the tenant's owner must always be one of them.
 *
 * Backfill: the user whose email matches this tenant's own
 * `tenants.owner_email` becomes admin (falling back to the very first
 * user if no email match is found, e.g. legacy data) - every tenant must
 * have at least one admin, and before this column existed the owner was
 * implicitly the only user anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
        });

        $ownerEmail = tenancy()->tenant?->owner_email;

        $adminId = $ownerEmail
            ? DB::table('users')->where('email', $ownerEmail)->value('id')
            : null;

        if (!$adminId) {
            $adminId = DB::table('users')->orderBy('id')->value('id');
        }

        if ($adminId) {
            DB::table('users')->where('id', $adminId)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
