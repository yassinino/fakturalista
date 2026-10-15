<?php

namespace App\Console\Commands;

use App\Models\BlogAutomationSetting;
use App\Models\BlogGeneration;
use App\Services\BlogAutomation\BlogAutomationService;
use Illuminate\Console\Command;

/**
 * Daily AI blog article (scheduled at 08:00 Africa/Casablanca, see
 * App\Console\Kernel). Reserves today's slot - the unique slot_key makes a
 * second run the same day a no-op - then queues GenerateBlogArticleJob.
 *
 *   php artisan blog:generate-daily            respects the backoffice switch
 *   php artisan blog:generate-daily --force    runs even if automation is off
 *   php artisan blog:generate-daily --sync     generates inline (no queue)
 */
class GenerateDailyBlogArticle extends Command
{
    protected $signature = 'blog:generate-daily
        {--force : Run even when the automation is disabled in the backoffice}
        {--sync : Generate in this process instead of dispatching the queued job}';

    protected $description = 'Generate (at most) one AI blog article for today';

    public function handle(BlogAutomationService $service): int
    {
        $settings = BlogAutomationSetting::current();

        if ($stale = $service->recoverStale()) {
            $this->warn("{$stale} génération(s) interrompue(s) marquée(s) en échec.");
        }

        if (! $settings->enabled && ! $this->option('force')) {
            $settings->recordRun('disabled', 'Automatisation désactivée : aucune génération.');
            $this->info('Automatisation désactivée dans le backoffice : rien à faire (utilisez --force pour tester).');

            return self::SUCCESS;
        }

        $generation = $service->claim($this->option('force') && ! $settings->enabled ? 'manual' : 'schedule');

        if (! $generation) {
            $today = $service->todaysGenerations()->last();
            $message = 'Limite quotidienne atteinte : article du jour déjà traité'
                . ($today ? ' (#' . $today->id . ', ' . $today->statusLabel() . ').' : '.');
            $settings->recordRun('already_done', $message);
            $this->info($message);

            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            $status = $service->run($generation->id);
            $generation->refresh();
            $this->line("Génération #{$generation->id} : " . (BlogGeneration::STATUS_LABELS[$status] ?? $status));
            if ($generation->article_title) {
                $this->line('Article : ' . $generation->article_title);
            }
            foreach ((array) $generation->draft_reasons as $reason) {
                $this->line('  - ' . $reason);
            }
            if ($generation->error_message) {
                $this->error($generation->error_message);
            }

            return $status === BlogGeneration::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
        }

        $service->dispatch($generation);
        $settings->recordRun('queued', "Génération #{$generation->id} mise en file.");
        $this->info("Génération #{$generation->id} mise en file d'attente.");

        return self::SUCCESS;
    }
}
