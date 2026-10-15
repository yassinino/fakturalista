<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-party website analytics (central database only - config/analytics.php).
 *
 * No IP address, cookie id or account is stored: `visitor_hash` is a daily,
 * salted hash that cannot be linked across days once the salt is destroyed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_pageviews', function (Blueprint $table) {
            $table->id();
            $table->timestamp('viewed_at');
            $table->date('view_date');                    // local day (analytics.timezone)
            $table->char('visitor_hash', 32);
            $table->boolean('is_entrance')->default(false); // first page view of a visit
            $table->string('path', 255);
            $table->string('title', 255)->nullable();
            $table->string('locale', 5)->nullable();
            $table->char('country_code', 2)->nullable();  // null = unknown
            $table->string('device', 10);                  // desktop | mobile | tablet
            $table->string('source', 12);                  // direct | search | social | ai | referral | campaign | internal
            $table->string('source_name', 100)->nullable(); // google, linkedin.com, utm_source...
            $table->string('referrer_host', 255)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();

            $table->index(['view_date', 'path']);
            $table->index(['view_date', 'visitor_hash']);
            $table->index(['visitor_hash', 'viewed_at']);
            $table->index(['view_date', 'is_entrance', 'source']);
            $table->index(['view_date', 'country_code']);
        });

        Schema::create('analytics_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);                    // free_trial_signup
            $table->timestamp('converted_at');
            $table->date('conversion_date');
            $table->char('visitor_hash', 32)->nullable();  // null when not attributable
            $table->char('country_code', 2)->nullable();
            $table->string('source', 12)->nullable();
            $table->string('source_name', 100)->nullable();

            $table->index(['conversion_date', 'type']);
        });

        // One random salt per day; deleted by analytics:prune afterwards.
        Schema::create('analytics_salts', function (Blueprint $table) {
            $table->date('salt_date')->primary();
            $table->string('salt', 64);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_salts');
        Schema::dropIfExists('analytics_conversions');
        Schema::dropIfExists('analytics_pageviews');
    }
};
