<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI blog automation (central database only - see config/blog_automation.php):
 *
 *  - posts gains the SEO fields the generator fills (primary keyword,
 *    secondary keywords, target country) and an `ai_generated` flag.
 *  - blog_automation_settings: one row, edited in the Filament page.
 *  - blog_generations: one row per generation run = execution log, topic
 *    history and daily idempotency (unique `slot_key`, "YYYY-MM-DD#n").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('posts', function (Blueprint $table) {
            $table->boolean('ai_generated')->default(false)->after('meta_description');
            $table->string('primary_keyword')->nullable()->after('ai_generated');
            $table->json('secondary_keywords')->nullable()->after('primary_keyword');
            $table->string('target_country', 2)->nullable()->after('secondary_keywords');
        });

        Schema::connection('mysql')->create('blog_automation_settings', function (Blueprint $table) {
            $table->id();
            // Safe defaults: nothing runs and nothing is published until an
            // administrator decides otherwise.
            $table->boolean('enabled')->default(false);
            $table->boolean('auto_publish')->default(false);
            $table->boolean('require_manual_approval')->default(true);
            $table->boolean('allow_regulatory_autopublish')->default(false);
            $table->boolean('ai_review')->default(true);
            $table->unsignedTinyInteger('max_per_day')->default(1);
            $table->json('target_countries')->nullable();
            $table->string('language', 5)->default('fr');
            $table->text('excluded_topics')->nullable();
            $table->string('notification_email')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status', 20)->nullable();
            $table->string('last_run_message', 500)->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql')->create('blog_generations', function (Blueprint $table) {
            $table->id();
            $table->date('run_date');
            $table->string('slot_key', 20)->unique();
            $table->string('trigger', 20);
            $table->string('status', 20)->index();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->string('country', 2)->nullable();
            $table->string('topic_key')->nullable()->index();
            $table->string('topic_category', 50)->nullable();
            $table->string('topic_title')->nullable();
            $table->string('primary_keyword')->nullable();
            $table->string('search_intent', 30)->nullable();
            $table->boolean('regulatory')->default(false);

            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('article_title')->nullable();
            $table->string('article_slug')->nullable();
            $table->unsignedSmallInteger('word_count')->nullable();

            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->json('draft_reasons')->nullable();
            $table->json('sources')->nullable();
            $table->boolean('sources_verified')->default(false);
            $table->json('ai_review')->nullable();
            $table->string('error_message', 1000)->nullable();

            $table->string('model', 100)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 5)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('failure_notified_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['run_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('blog_generations');
        Schema::connection('mysql')->dropIfExists('blog_automation_settings');
        Schema::connection('mysql')->table('posts', function (Blueprint $table) {
            $table->dropColumn(['ai_generated', 'primary_keyword', 'secondary_keywords', 'target_country']);
        });
    }
};
