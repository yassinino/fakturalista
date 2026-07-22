<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('status');
            $table->unsignedBigInteger('source_invoice_id')->nullable()->after('issued_at');
        });

        // Migrate existing status values: '1'=paid, '0'=issued (sent but unpaid), null=draft
        DB::table('invoices')->where('status', '1')->update(['status' => 'paid']);
        DB::table('invoices')->where('status', '0')->update(['status' => 'issued']);
        DB::table('invoices')->whereNull('status')->orWhereNotIn('status', ['paid', 'issued', 'draft', 'cancelled'])->update(['status' => 'draft']);
    }

    public function down(): void
    {
        // Revert status values before dropping columns
        DB::table('invoices')->where('status', 'paid')->update(['status' => '1']);
        DB::table('invoices')->where('status', 'issued')->update(['status' => '0']);
        DB::table('invoices')->whereIn('status', ['draft', 'cancelled'])->update(['status' => null]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['issued_at', 'source_invoice_id']);
        });
    }
};
