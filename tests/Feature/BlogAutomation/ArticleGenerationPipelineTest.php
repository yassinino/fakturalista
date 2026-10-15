<?php

namespace Tests\Feature\BlogAutomation;

use App\Jobs\GenerateBlogArticleJob;
use App\Mail\BlogArticleReadyMail;
use App\Mail\BlogGenerationFailingMail;
use App\Models\BlogGeneration;
use App\Models\Post;
use App\Services\BlogAutomation\BlogAutomationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/** End-to-end generation with a mocked Gemini API. */
class ArticleGenerationPipelineTest extends BlogAutomationTestCase
{
    private function run_(BlogGeneration $generation, bool $final = true): string
    {
        return app(BlogAutomationService::class)->run($generation->id, $final);
    }

    private function sitemapLocs(): array
    {
        preg_match_all('~<loc>([^<]+)</loc>~', $this->get(self::HOST . '/sitemap.xml')->assertOk()->getContent(), $m);

        return array_map('html_entity_decode', $m[1]);
    }

    public function test_default_settings_save_a_draft_with_seo_metadata_and_notify_once(): void
    {
        $this->fakeGemini();
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_DRAFT, $this->run_($generation));

        $generation->refresh();
        $post = $generation->post;
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);
        $this->assertTrue($post->ai_generated);
        $this->assertSame('ma', $post->target_country);
        $this->assertSame('suivre-encaissements-clients-methode-tpe', $post->slug);
        $this->assertSame('Suivi des encaissements clients : méthode pour une TPE', $post->getRawOriginal('meta_title'));
        $this->assertSame('suivi encaissements clients', $post->primary_keyword);
        $this->assertSame(['suivi des paiements clients', 'relance client', 'trésorerie TPE'], $post->secondary_keywords);
        $this->assertContains('Publication automatique désactivée (mode brouillon).', $generation->draft_reasons);
        $this->assertSame([], $generation->validation_errors);
        $this->assertSame(4200 * 2, $generation->total_tokens, 'Writer + reviewer usage is recorded.');
        $this->assertSame('gemini-test-model', $generation->model);
        $this->assertNull($generation->estimated_cost_usd, 'No price configured: no invented cost.');

        Mail::assertSent(BlogArticleReadyMail::class, 1);
        Mail::assertSent(BlogArticleReadyMail::class, fn ($mail) => $mail->hasTo('admin@example.com')
            && str_contains($mail->render(), '/backoffice/blog-generations/' . $generation->id));

        $this->assertNotContains('https://fakturalista.com/blog/' . $post->slug, $this->sitemapLocs(), 'Drafts stay out of the sitemap.');
        $this->get(self::HOST . '/blog/' . $post->slug)->assertNotFound();
    }

    public function test_a_valid_article_is_published_when_auto_publication_is_allowed(): void
    {
        $this->fakeGemini();
        $this->settings(['auto_publish' => true, 'require_manual_approval' => false]);
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_PUBLISHED, $this->run_($generation));

        $post = $generation->fresh()->post;
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertContains('https://fakturalista.com/blog/' . $post->slug, $this->sitemapLocs());

        $html = $this->get(self::HOST . '/blog/' . $post->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://fakturalista.com/blog/' . $post->slug . '"', $html);
        $this->assertStringContainsString('"BlogPosting"', $html);
        $this->assertStringNotContainsString('FAQPage', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    public function test_cost_is_estimated_only_from_configured_prices(): void
    {
        config(['blog_automation.gemini.price_input_per_million' => 0.5, 'blog_automation.gemini.price_output_per_million' => 2.0]);
        $this->fakeGemini();
        $generation = $this->pendingGeneration();

        $this->run_($generation);

        // 2 calls x (1 200 in + 3 000 out)
        $this->assertEquals(round(2400 / 1e6 * 0.5 + 6000 / 1e6 * 2.0, 5), (float) $generation->fresh()->estimated_cost_usd);
    }

    public function test_manual_approval_keeps_valid_articles_as_drafts(): void
    {
        $this->fakeGemini();
        $this->settings(['auto_publish' => true, 'require_manual_approval' => true]);

        $this->assertSame(BlogGeneration::STATUS_DRAFT, $this->run_($this->pendingGeneration()));
    }

    public function test_failed_quality_checks_never_publish(): void
    {
        $this->fakeGemini($this->article(['body_html' => '<h2>Bref</h2><p>Trop court [Nom du client].</p>']));
        $this->settings(['auto_publish' => true, 'require_manual_approval' => false]);
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_DRAFT, $this->run_($generation));
        $generation->refresh();
        $this->assertNotEmpty($generation->validation_errors);
        $this->assertSame('draft', $generation->post->status);
        Http::assertSentCount(1); // no AI review on an article that already failed
    }

    public function test_the_ai_review_can_block_but_not_approve(): void
    {
        $this->fakeGemini(null, ['verdict' => 'needs_review', 'issues' => ['Le délai de 60 jours est à vérifier.']]);
        $this->settings(['auto_publish' => true, 'require_manual_approval' => false]);
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_DRAFT, $this->run_($generation));
        $this->assertSame(['Le délai de 60 jours est à vérifier.'], $generation->fresh()->ai_review['issues']);
    }

    public function test_regulatory_content_without_verified_sources_stays_a_draft(): void
    {
        $this->fakeGemini($this->article(['body_html' => $this->article()['body_html'] . '<p>Selon l\'article 145 du CGI, ces mentions sont obligatoires.</p>']));
        $this->settings(['auto_publish' => true, 'require_manual_approval' => false, 'allow_regulatory_autopublish' => true]);
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_DRAFT, $this->run_($generation));
        $generation->refresh();
        $this->assertTrue($generation->regulatory);
        $this->assertFalse($generation->sources_verified);
        $this->assertContains('Contenu réglementaire sans source officielle vérifiée : relecture humaine obligatoire.', $generation->draft_reasons);
    }

    public function test_verified_regulatory_content_still_needs_the_dedicated_switch(): void
    {
        $body = $this->article()['body_html'] . '<p>Selon l\'article 145 du CGI (<a href="https://www.tax.gov.ma/wps/portal/DGI">DGI</a>), ces mentions sont obligatoires.</p>';
        $this->fakeGemini($this->article(['body_html' => $body]), grounding: [['uri' => 'https://vertexaisearch.cloud.google.com/r/1', 'title' => 'tax.gov.ma']]);
        $this->settings(['auto_publish' => true, 'require_manual_approval' => false, 'allow_regulatory_autopublish' => false]);

        $generation = $this->pendingGeneration(['regulatory' => true]);
        $this->assertSame(BlogGeneration::STATUS_DRAFT, $this->run_($generation));
        $this->assertTrue($generation->fresh()->sources_verified);

        // Same article again: drop the first copy so it isn't a duplicate.
        $generation->fresh()->post->delete();
        $this->settings(['allow_regulatory_autopublish' => true]);
        $second = $this->pendingGeneration(['slot_key' => 'other#1', 'regulatory' => true]);
        $this->assertSame(BlogGeneration::STATUS_PUBLISHED, $this->run_($second));
    }

    public function test_requests_are_bounded_grounded_and_keep_the_key_out_of_the_url(): void
    {
        $this->fakeGemini();
        $this->run_($this->pendingGeneration());

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'models/gemini-test-model:generateContent')
                && ! str_contains($request->url(), 'test-gemini-key')
                && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
                && isset($request->data()['tools'][0]['google_search'])
                && $request->data()['generationConfig']['maxOutputTokens'] <= config('blog_automation.gemini.max_output_tokens');
        });
    }

    public function test_duplicate_slugs_get_a_suffix(): void
    {
        Post::create(['title' => 'Existant', 'slug' => 'suivre-encaissements-clients-methode-tpe', 'body' => '<p>x</p>', 'status' => 'draft']);
        $this->fakeGemini();
        $generation = $this->pendingGeneration();

        $this->run_($generation);

        $this->assertSame('suivre-encaissements-clients-methode-tpe-2', $generation->fresh()->post->slug);
    }

    public function test_a_missing_api_key_fails_cleanly_without_calling_gemini(): void
    {
        config(['services.gemini.key' => '']);
        Http::fake();
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_FAILED, $this->run_($generation));
        $this->assertStringContainsString('GEMINI_API_KEY', $generation->fresh()->error_message);
        Http::assertNothingSent();
        $this->assertSame(0, Post::where('ai_generated', true)->where('created_at', '>=', now()->subMinute())->count());
    }

    public function test_server_errors_are_retried_then_fail_without_leaking_details(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'secret internals']], 503)]);
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_FAILED, $this->run_($generation));

        Http::assertSentCount(1 + config('blog_automation.gemini.max_retries'));
        $message = $generation->fresh()->error_message;
        $this->assertStringContainsString('503', $message);
        $this->assertStringNotContainsString('secret internals', $message);
        $this->assertStringNotContainsString('test-gemini-key', $message);
    }

    public function test_a_rate_limit_is_retried_and_can_succeed(): void
    {
        $article = $this->geminiBody($this->article());
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429, ['Retry-After' => '1'])
            ->push($article)
            ->push($this->geminiBody(['verdict' => 'pass', 'issues' => []]));

        $this->assertSame(BlogGeneration::STATUS_DRAFT, $this->run_($this->pendingGeneration()));
    }

    public function test_a_transient_error_on_a_non_final_attempt_asks_the_queue_to_retry(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
        $generation = $this->pendingGeneration();

        $this->assertSame('retry', $this->run_($generation, false));
        $this->assertSame(BlogGeneration::STATUS_PENDING, $generation->fresh()->status);
    }

    public function test_invalid_json_fails(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiBody('Désolé, je ne peux pas.'))]);
        $generation = $this->pendingGeneration();

        $this->assertSame(BlogGeneration::STATUS_FAILED, $this->run_($generation));
        $this->assertStringContainsString('JSON invalide', $generation->fresh()->error_message);
    }

    public function test_blocked_responses_fail(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);
        $blocked = $this->pendingGeneration(['slot_key' => 'blocked#1']);
        $this->assertSame(BlogGeneration::STATUS_FAILED, $this->run_($blocked));
        $this->assertStringContainsString('SAFETY', $blocked->fresh()->error_message);
    }

    public function test_repeated_failures_alert_the_admin_once_per_streak(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 400)]);
        $service = app(BlogAutomationService::class);

        $service->run($this->pendingGeneration(['slot_key' => 'f#1'])->id);
        Mail::assertNotSent(BlogGenerationFailingMail::class);

        $service->run($this->pendingGeneration(['slot_key' => 'f#2'])->id);
        Mail::assertSent(BlogGenerationFailingMail::class, 1);

        $service->run($this->pendingGeneration(['slot_key' => 'f#3'])->id);
        Mail::assertSent(BlogGenerationFailingMail::class, 1);
    }

    public function test_approve_publishes_and_reject_discards(): void
    {
        $this->fakeGemini();
        $service = app(BlogAutomationService::class);

        $first = $this->pendingGeneration();
        $service->run($first->id);
        $post = $service->approve($first->fresh(), null);
        $this->assertSame('published', $post->status);
        $this->assertSame(BlogGeneration::STATUS_PUBLISHED, $first->fresh()->status);
        $this->assertContains('https://fakturalista.com/blog/' . $post->slug, $this->sitemapLocs());

        $this->fakeGemini($this->article(['slug' => 'autre-article', 'title' => 'Un autre titre', 'meta_title' => 'Un autre titre SEO pour les encaissements', 'meta_description' => 'Une autre description, assez longue pour respecter les bornes minimales du contrôle qualité, sur le suivi des encaissements clients.']));
        $second = $this->pendingGeneration(['slot_key' => 'second#1']);
        $service->run($second->id);
        $postId = $second->fresh()->post_id;
        $service->reject($second->fresh(), null);
        $this->assertSame(BlogGeneration::STATUS_REJECTED, $second->fresh()->status);
        $this->assertNull(Post::find($postId));
    }

    public function test_publishing_a_draft_from_the_post_form_closes_its_generation(): void
    {
        $this->fakeGemini();
        $generation = $this->pendingGeneration();
        app(BlogAutomationService::class)->run($generation->id);

        $generation->fresh()->post->update(['status' => 'published', 'published_at' => now()]);

        $this->assertSame(BlogGeneration::STATUS_PUBLISHED, $generation->fresh()->status);
    }

    public function test_retry_requeues_only_failed_generations_in_the_same_slot(): void
    {
        Queue::fake();
        $service = app(BlogAutomationService::class);
        $failed = $this->pendingGeneration(['status' => BlogGeneration::STATUS_FAILED]);
        $draft = $this->pendingGeneration(['slot_key' => 'd#1', 'status' => BlogGeneration::STATUS_DRAFT]);

        $this->assertTrue($service->retry($failed));
        $this->assertFalse($service->retry($draft));
        Queue::assertPushed(GenerateBlogArticleJob::class, 1);
        $this->assertSame(BlogGeneration::STATUS_PENDING, $failed->fresh()->status);
        $this->assertSame(2, BlogGeneration::count());
    }

    public function test_everything_is_stored_in_the_central_database(): void
    {
        $this->assertSame('mysql', (new BlogGeneration())->getConnectionName());
        $this->assertSame('mysql', (new Post())->getConnectionName());
        $this->assertSame('mysql', (new \App\Models\BlogAutomationSetting())->getConnectionName());

        $this->fakeGemini();
        $this->run_($this->pendingGeneration());

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(config('database.connections.mysql.database'), \DB::connection('mysql')->getDatabaseName());
    }
}
