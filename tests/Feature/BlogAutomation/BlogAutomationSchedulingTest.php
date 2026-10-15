<?php

namespace Tests\Feature\BlogAutomation;

use App\Jobs\GenerateBlogArticleJob;
use App\Models\BlogGeneration;
use App\Services\BlogAutomation\BlogAutomationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

/** Scheduler entry, the daily command and duplicate-execution prevention. */
class BlogAutomationSchedulingTest extends BlogAutomationTestCase
{
    public function test_the_command_is_scheduled_daily_at_8am_casablanca_without_overlap(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'blog:generate-daily'));

        $this->assertCount(1, $events, 'blog:generate-daily must be scheduled exactly once.');
        $event = $events->first();
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Africa/Casablanca', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->runInBackground);
    }

    public function test_the_command_does_nothing_while_the_automation_is_disabled(): void
    {
        Queue::fake();
        $this->settings(['enabled' => false]);

        $this->artisan('blog:generate-daily')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(0, BlogGeneration::count());
        $this->assertSame('disabled', $this->settings()->last_run_status);
    }

    public function test_the_command_reserves_todays_slot_and_queues_one_job(): void
    {
        Queue::fake();
        $this->settings(['enabled' => true]);

        $this->artisan('blog:generate-daily')->assertSuccessful();

        Queue::assertPushed(GenerateBlogArticleJob::class, 1);
        $generation = BlogGeneration::sole();
        $this->assertSame('2031-03-10#1', $generation->slot_key);
        $this->assertSame('schedule', $generation->trigger);
        $this->assertSame(BlogGeneration::STATUS_PENDING, $generation->status);
    }

    public function test_a_second_run_the_same_day_is_a_no_op(): void
    {
        Queue::fake();
        $this->settings(['enabled' => true]);

        $this->artisan('blog:generate-daily')->assertSuccessful();
        $this->artisan('blog:generate-daily')->assertSuccessful();
        $this->artisan('blog:generate-daily', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(GenerateBlogArticleJob::class, 1);
        $this->assertSame(1, BlogGeneration::count());
        $this->assertSame('already_done', $this->settings()->last_run_status);
    }

    public function test_the_daily_limit_is_enforced_by_the_database_even_if_settings_say_more(): void
    {
        $this->settings(['max_per_day' => 5]);
        $service = app(BlogAutomationService::class);

        $this->assertNotNull($service->claim('schedule'));
        $this->assertNull($service->claim('manual'));
        $this->assertSame(1, BlogGeneration::count());

        // Next day: a new slot is available.
        $this->travel(1)->days();
        $this->assertNotNull($service->claim('schedule'));
    }

    public function test_the_day_is_counted_in_casablanca_time(): void
    {
        // 23:30 UTC on 9 March is already 10 March 00:30 in Casablanca (UTC+1).
        $this->travelTo(\Illuminate\Support\Carbon::parse('2031-03-09 23:30:00', 'UTC'));
        $generation = app(BlogAutomationService::class)->claim('schedule');

        $this->assertSame('2031-03-10#1', $generation->slot_key);
    }

    public function test_a_generation_row_can_only_be_run_once_at_a_time(): void
    {
        $generation = $this->pendingGeneration(['status' => BlogGeneration::STATUS_RUNNING, 'started_at' => now()]);

        $this->assertSame('locked', app(BlogAutomationService::class)->run($generation->id));
    }

    public function test_stale_running_generations_are_recovered_as_failed(): void
    {
        $generation = $this->pendingGeneration(['status' => BlogGeneration::STATUS_RUNNING, 'started_at' => now()->subHours(2)]);

        $this->assertSame(1, app(BlogAutomationService::class)->recoverStale());
        $this->assertSame(BlogGeneration::STATUS_FAILED, $generation->fresh()->status);
    }

    public function test_sync_option_generates_inline(): void
    {
        $this->fakeGemini();
        $this->settings(['enabled' => true]);

        $this->artisan('blog:generate-daily', ['--sync' => true])->assertSuccessful();

        $this->assertSame(BlogGeneration::STATUS_DRAFT, BlogGeneration::sole()->status);
    }
}
