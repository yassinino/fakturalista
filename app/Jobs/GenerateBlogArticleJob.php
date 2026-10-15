<?php

namespace App\Jobs;

use App\Models\BlogGeneration;
use App\Services\BlogAutomation\BlogAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Throwable;

/**
 * Generates one AI blog article (BlogAutomationService::run) for a reserved
 * generation row. Central database only - never runs in tenant context.
 *
 * Transient Gemini errors (429, 5xx, network) are retried with a backoff on
 * a real queue; on the sync driver the single attempt is final.
 */
class GenerateBlogArticleJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    /** Seconds before each retry of a transient failure. */
    public array $backoff = [300, 900];

    public function __construct(public readonly int $generationId) {}

    public function uniqueId(): string
    {
        return 'blog-generation-' . $this->generationId;
    }

    public function handle(BlogAutomationService $service): void
    {
        // Several bounded Gemini calls + link checks: lift the web time limit
        // when the job runs after an HTTP response (sync queue).
        @set_time_limit($this->timeout);

        $final = ! $this->job || $this->job instanceof SyncJob || $this->attempts() >= $this->tries;

        if ($service->run($this->generationId, $final) === 'retry') {
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
        }
    }

    public function failed(?Throwable $e): void
    {
        BlogGeneration::whereKey($this->generationId)
            ->whereIn('status', [BlogGeneration::STATUS_PENDING, BlogGeneration::STATUS_RUNNING])
            ->update([
                'status'        => BlogGeneration::STATUS_FAILED,
                'error_message' => 'Le job de génération a échoué (' . ($e ? class_basename($e) : 'inconnu') . ').',
                'finished_at'   => now(),
            ]);
    }
}
