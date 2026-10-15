<?php

namespace App\Services\BlogAutomation;

use App\Jobs\GenerateBlogArticleJob;
use App\Mail\BlogArticleReadyMail;
use App\Mail\BlogGenerationFailingMail;
use App\Models\AdminUser;
use App\Models\BlogAutomationSetting;
use App\Models\BlogGeneration;
use App\Models\Post;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The only writer of blog_generations and of AI posts.
 *
 *  claim()   reserves today's slot (unique slot_key = DB-level idempotency,
 *            at most `max_per_day_cap` new articles per Morocco day);
 *  run()     topic -> Gemini -> sanitize -> validate -> (AI review) ->
 *            save as draft or publish -> notify. Called by the queued job;
 *  approve() / reject() / retry() back the Filament actions.
 *
 * Publication is automatic only when the backoffice allows it AND every
 * deterministic check passed AND the AI review (if enabled) found nothing
 * AND, for regulatory content, official sources were verified and the
 * dedicated switch is on. Everything else stays a draft with its reasons.
 */
class BlogAutomationService
{
    public function __construct(private readonly GeminiClient $gemini) {}

    public function today(): Carbon
    {
        return now(config('blog_automation.timezone'))->startOfDay();
    }

    /** Generations reserved today (any status). */
    public function todaysGenerations()
    {
        return BlogGeneration::whereDate('run_date', $this->today()->toDateString())->orderBy('id')->get();
    }

    /**
     * Reserves a free slot of today, or returns null when the daily limit is
     * reached (also under concurrent calls: the unique index decides).
     */
    public function claim(string $trigger): ?BlogGeneration
    {
        $settings = BlogAutomationSetting::current();
        $date = $this->today()->toDateString();

        for ($slot = 1; $slot <= $settings->dailyLimit(); $slot++) {
            try {
                return BlogGeneration::create([
                    'run_date' => $date,
                    'slot_key' => "{$date}#{$slot}",
                    'trigger'  => $trigger,
                    'status'   => BlogGeneration::STATUS_PENDING,
                    'model'    => $this->gemini->model(),
                ]);
            } catch (QueryException $e) {
                if (($e->errorInfo[0] ?? null) !== '23000') {
                    throw $e;
                }
            }
        }

        return null;
    }

    /** Queues the generation (after the HTTP response when the queue is synchronous). */
    public function dispatch(BlogGeneration $generation): void
    {
        $job = (new GenerateBlogArticleJob($generation->id))->onQueue(config('blog_automation.queue'));

        if (config('queue.default') === 'sync' && ! app()->runningInConsole()) {
            dispatch($job)->afterResponse();
        } else {
            dispatch($job);
        }
    }

    /** Marks runs stuck in "running" (killed worker, timeout) as failed. */
    public function recoverStale(): int
    {
        return BlogGeneration::where('status', BlogGeneration::STATUS_RUNNING)
            ->where('started_at', '<', now()->subMinutes((int) config('blog_automation.stale_after_minutes')))
            ->update([
                'status'        => BlogGeneration::STATUS_FAILED,
                'error_message' => 'Génération interrompue (délai dépassé ou processus arrêté).',
                'finished_at'   => now(),
            ]);
    }

    /**
     * Runs one generation. Returns the final status, or 'retry' when a
     * transient Gemini error should be retried later by the queue.
     */
    public function run(int $generationId, bool $finalAttempt = true): string
    {
        // Atomic pending -> running: a second worker on the same row stops here.
        $claimed = BlogGeneration::whereKey($generationId)
            ->where('status', BlogGeneration::STATUS_PENDING)
            ->update([
                'status'        => BlogGeneration::STATUS_RUNNING,
                'started_at'    => now(),
                'attempts'      => DB::raw('attempts + 1'),
                'error_message' => null,
            ]);
        if (! $claimed) {
            return 'locked';
        }

        $generation = BlogGeneration::findOrFail($generationId);
        $settings = BlogAutomationSetting::current();
        $generator = new ArticleGenerator($this->gemini);

        try {
            $topic = $this->topicFor($generation, $settings, $generator);
            if (! $topic) {
                $generation->update([
                    'status'        => BlogGeneration::STATUS_SKIPPED,
                    'error_message' => 'Aucun sujet suffisamment différent des articles existants : pas de publication aujourd\'hui.',
                    'finished_at'   => now(),
                ]);
                $settings->recordRun('skipped', 'Aucun sujet unique disponible.');

                return BlogGeneration::STATUS_SKIPPED;
            }

            if (! $this->gemini->isConfigured()) {
                throw new GeminiException('GEMINI_API_KEY n\'est pas configurée.');
            }

            $result = $generator->write($topic);
            $status = $this->store($generation->fresh(), $settings, $topic, $result, $generator);
            $settings->recordRun($status, $generation->fresh()->article_title ?? '');
            $this->notifyReady($generation->fresh());

            return $status;
        } catch (GeminiException $e) {
            if ($e->retryable && ! $finalAttempt) {
                $this->saveUsage($generation, $generator, [
                    'status'        => BlogGeneration::STATUS_PENDING,
                    'error_message' => 'Nouvel essai prévu : ' . $e->getMessage(),
                ]);

                return 'retry';
            }

            return $this->fail($generation, $settings, $generator, $e->getMessage());
        } catch (Throwable $e) {
            Log::error('[BlogAutomation] Generation crashed', ['generation' => $generationId, 'exception' => $e::class, 'message' => Str::limit($e->getMessage(), 300)]);

            return $this->fail($generation, $settings, $generator, 'Erreur interne : ' . Str::limit($e->getMessage(), 300));
        }
    }

    private function topicFor(BlogGeneration $generation, BlogAutomationSetting $settings, ArticleGenerator $generator): ?array
    {
        // A retried run keeps the topic it already reserved.
        if ($generation->topic_key) {
            return [
                'key'        => $generation->topic_key,
                'country'    => $generation->country,
                'category'   => $generation->topic_category,
                'title'      => $generation->topic_title,
                'keyword'    => $generation->primary_keyword,
                'intent'     => $generation->search_intent ?? 'informational',
                'regulatory' => $generation->regulatory,
                'source'     => 'history',
            ];
        }

        $selector = new TopicSelector($this->gemini->isConfigured() ? fn ($country, $context) => $generator->ideate($country, $context) : null);
        $topic = $selector->select($settings);

        if ($topic) {
            $generation->update([
                'country'         => $topic['country'],
                'topic_key'       => $topic['key'],
                'topic_category'  => $topic['category'],
                'topic_title'     => $topic['title'],
                'primary_keyword' => $topic['keyword'],
                'search_intent'   => $topic['intent'],
                'regulatory'      => $topic['regulatory'],
            ]);
        }

        return $topic;
    }

    private function store(BlogGeneration $generation, BlogAutomationSetting $settings, array $topic, array $result, ArticleGenerator $generator): string
    {
        $article = $result['article'];
        $article['slug'] = $this->uniqueSlug($article['slug'] ?: $article['title']);

        $policy = new SourcePolicy((bool) config('blog_automation.check_links', true));
        $policy->prefetch($article['body']);
        $sanitized = ArticleHtml::sanitize($article['body'], $policy);
        $article['body'] = $sanitized['html'];

        $report = (new ArticleValidator())->validate($article, $topic, $sanitized['links'], $result['sources']);

        // Optional second opinion - only when the article could otherwise go out.
        $review = null;
        if ($settings->ai_review && $report->passes()) {
            try {
                $review = $generator->review($article, $topic);
            } catch (GeminiException $e) {
                $review = ['verdict' => 'unavailable', 'issues' => ['Relecture IA indisponible : ' . $e->getMessage()]];
            }
        }

        $reasons = $this->draftReasons($settings, $report, $review);
        $publish = $reasons === [];

        $sources = [
            'grounding' => array_slice($result['sources'], 0, 20),
            'queries'   => array_slice($result['search_queries'], 0, 10),
            'cited'     => array_slice(array_values(array_filter(array_map(fn ($s) => is_array($s) && isset($s['url']) ? ['title' => (string) ($s['title'] ?? ''), 'url' => (string) $s['url']] : null, $article['sources']))), 0, 15),
            'official'  => $report->officialCitations,
        ];

        DB::connection('mysql')->transaction(function () use ($generation, $generator, $article, $topic, $report, $review, $reasons, $publish, $sources) {
            $post = Post::create([
                'title'              => Str::limit($article['title'], 250, ''),
                'slug'               => $article['slug'],
                'excerpt'            => Str::limit($article['excerpt'], 500, ''),
                'body'               => $article['body'],
                'status'             => $publish ? 'published' : 'draft',
                'published_at'       => $publish ? now() : null,
                'meta_title'         => Str::limit($article['meta_title'], 250, ''),
                'meta_description'   => $article['meta_description'],
                'ai_generated'       => true,
                'primary_keyword'    => Str::limit($article['primary_keyword'], 250, ''),
                'secondary_keywords' => $article['secondary_keywords'],
                'target_country'     => $topic['country'],
            ]);

            $this->saveUsage($generation, $generator, [
                'status'              => $publish ? BlogGeneration::STATUS_PUBLISHED : BlogGeneration::STATUS_DRAFT,
                'post_id'             => $post->id,
                'article_title'       => Str::limit($post->title, 250, ''),
                'article_slug'        => $post->slug,
                'word_count'          => min($report->wordCount, 65535),
                'regulatory'          => $report->regulatory,
                'sources_verified'    => $report->sourcesVerified,
                'validation_errors'   => $report->errors,
                'validation_warnings' => $report->warnings,
                'draft_reasons'       => $reasons,
                'sources'             => $sources,
                'ai_review'           => $review,
                'error_message'       => null,
            ]);
        });

        return $publish ? BlogGeneration::STATUS_PUBLISHED : BlogGeneration::STATUS_DRAFT;
    }

    /** @return string[] why the article is kept as a draft (empty = publish). */
    public function draftReasons(BlogAutomationSetting $settings, ValidationReport $report, ?array $review): array
    {
        $reasons = [];

        if (! $settings->auto_publish) {
            $reasons[] = 'Publication automatique désactivée (mode brouillon).';
        } elseif ($settings->require_manual_approval) {
            $reasons[] = 'Validation manuelle requise.';
        }
        if (! $report->passes()) {
            $reasons[] = 'Contrôle qualité non réussi (' . count($report->errors) . ' erreur(s)).';
        }
        if ($report->regulatory) {
            if (! $report->sourcesVerified) {
                $reasons[] = 'Contenu réglementaire sans source officielle vérifiée : relecture humaine obligatoire.';
            } elseif (! $settings->allow_regulatory_autopublish) {
                $reasons[] = 'Contenu réglementaire : publication automatique non autorisée dans les réglages.';
            }
        }
        if ($settings->ai_review && $report->passes()) {
            if (($review['verdict'] ?? null) === 'needs_review') {
                $reasons[] = 'La relecture IA signale des points à vérifier.';
            } elseif (($review['verdict'] ?? null) !== 'pass') {
                $reasons[] = 'Relecture IA indisponible.';
            }
        }

        return $reasons;
    }

    private function fail(BlogGeneration $generation, BlogAutomationSetting $settings, ArticleGenerator $generator, string $message): string
    {
        $this->saveUsage($generation, $generator, [
            'status'        => BlogGeneration::STATUS_FAILED,
            'error_message' => Str::limit($message, 1000),
        ]);
        $settings->recordRun('failed', $message);
        $this->notifyFailureStreak($generation->fresh());

        return BlogGeneration::STATUS_FAILED;
    }

    private function saveUsage(BlogGeneration $generation, ArticleGenerator $generator, array $attributes): void
    {
        $prompt = $generation->prompt_tokens + $generator->promptTokens;
        $output = $generation->output_tokens + $generator->outputTokens;
        $in = config('blog_automation.gemini.price_input_per_million');
        $out = config('blog_automation.gemini.price_output_per_million');

        $generation->update($attributes + [
            'model'              => $generator->model ?? $generation->model ?? $this->gemini->model(),
            'prompt_tokens'      => $prompt,
            'output_tokens'      => $output,
            'total_tokens'       => $generation->total_tokens + $generator->totalTokens,
            'estimated_cost_usd' => is_numeric($in) && is_numeric($out) ? round($prompt / 1e6 * $in + $output / 1e6 * $out, 5) : null,
            'finished_at'        => now(),
        ]);
    }

    public function uniqueSlug(string $source, ?int $ignorePostId = null): string
    {
        $base = Str::slug($source) ?: 'article';
        if (strlen($base) > 80) {
            // Cut on a word boundary.
            $cut = substr($base, 0, 81);
            $base = substr($cut, 0, strrpos($cut, '-') ?: 80);
        }
        $slug = $base;

        for ($i = 2; Post::where('slug', $slug)->when($ignorePostId, fn ($q) => $q->whereKeyNot($ignorePostId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    // ── Backoffice actions ──────────────────────────────────────────────

    public function approve(BlogGeneration $generation, ?AdminUser $admin): Post
    {
        $post = $generation->post;
        if (! $post || $generation->status !== BlogGeneration::STATUS_DRAFT) {
            throw new RuntimeException('Seul un article en attente de relecture peut être publié.');
        }

        DB::connection('mysql')->transaction(function () use ($generation, $post, $admin) {
            $post->update(['status' => 'published', 'published_at' => $post->published_at ?? now()]);
            $generation->update([
                'status'      => BlogGeneration::STATUS_PUBLISHED,
                'reviewed_at' => now(),
                'reviewed_by' => $admin?->id,
            ]);
        });

        return $post->fresh();
    }

    /** Discards a draft: the post is deleted, the generation stays as topic history. */
    public function reject(BlogGeneration $generation, ?AdminUser $admin): void
    {
        if ($generation->status !== BlogGeneration::STATUS_DRAFT) {
            throw new RuntimeException('Seul un article en attente de relecture peut être rejeté.');
        }

        DB::connection('mysql')->transaction(function () use ($generation, $admin) {
            $post = $generation->post;
            if ($post && $post->status !== 'published') {
                $post->delete();
            }
            $generation->update([
                'status'      => BlogGeneration::STATUS_REJECTED,
                'post_id'     => null,
                'reviewed_at' => now(),
                'reviewed_by' => $admin?->id,
            ]);
        });
    }

    /** Re-queues a failed generation in its own slot (no extra article for the day). */
    public function retry(BlogGeneration $generation): bool
    {
        $updated = BlogGeneration::whereKey($generation->id)
            ->where('status', BlogGeneration::STATUS_FAILED)
            ->update(['status' => BlogGeneration::STATUS_PENDING, 'error_message' => null, 'trigger' => 'manual']);

        if ($updated) {
            $this->dispatch($generation->fresh());
        }

        return (bool) $updated;
    }

    // ── Notifications (each at most once per generation / failure streak) ──

    private function notifyReady(BlogGeneration $generation): void
    {
        if ($generation->notified_at || ! in_array($generation->status, [BlogGeneration::STATUS_DRAFT, BlogGeneration::STATUS_PUBLISHED], true)) {
            return;
        }
        if (BlogGeneration::whereKey($generation->id)->whereNull('notified_at')->update(['notified_at' => now()])) {
            $this->send(new BlogArticleReadyMail($generation));
        }
    }

    private function notifyFailureStreak(BlogGeneration $generation): void
    {
        $threshold = max(1, (int) config('blog_automation.failure_alert_threshold'));
        $streak = [];
        foreach (BlogGeneration::orderByDesc('id')->limit(30)->get(['id', 'status', 'failure_notified_at']) as $row) {
            if ($row->status !== BlogGeneration::STATUS_FAILED) {
                break;
            }
            $streak[] = $row;
        }

        if (count($streak) < $threshold || collect($streak)->contains(fn ($row) => $row->failure_notified_at !== null)) {
            return;
        }
        if (BlogGeneration::whereKey($generation->id)->whereNull('failure_notified_at')->update(['failure_notified_at' => now()])) {
            $this->send(new BlogGenerationFailingMail($generation, count($streak)));
        }
    }

    private function send($mailable): void
    {
        try {
            Mail::to(BlogAutomationSetting::current()->notificationEmail())->send($mailable);
        } catch (Throwable $e) {
            Log::warning('[BlogAutomation] Notification not sent', ['exception' => $e::class]);
        }
    }

    /** Dashboard figures for the Filament page. */
    public function stats(): array
    {
        $counts = BlogGeneration::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'generated'  => (int) $counts->only([BlogGeneration::STATUS_DRAFT, BlogGeneration::STATUS_PUBLISHED, BlogGeneration::STATUS_REJECTED])->sum(),
            'published'  => (int) ($counts[BlogGeneration::STATUS_PUBLISHED] ?? 0),
            'awaiting'   => BlogGeneration::where('status', BlogGeneration::STATUS_DRAFT)->whereNotNull('post_id')->count(),
            'failed'     => (int) ($counts[BlogGeneration::STATUS_FAILED] ?? 0),
            'rejected'   => (int) ($counts[BlogGeneration::STATUS_REJECTED] ?? 0),
            'tokens_30d' => (int) BlogGeneration::where('created_at', '>=', now()->subDays(30))->sum('total_tokens'),
            'cost_30d'   => BlogGeneration::where('created_at', '>=', now()->subDays(30))->whereNotNull('estimated_cost_usd')->sum('estimated_cost_usd'),
            'last'       => BlogGeneration::latest('id')->first(),
        ];
    }
}
