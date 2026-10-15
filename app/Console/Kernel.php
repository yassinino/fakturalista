<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        $schedule->command('billing:send-reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        // Settings > Notifications - per-invoice due-soon/overdue reminders.
        // Same daily scheduler, a separate command (see
        // SendInvoiceRemindersCommand's docblock for why), staggered a few
        // minutes later purely to avoid two commands touching every
        // tenant's database at the exact same moment.
        $schedule->command('invoices:send-reminders')
            ->dailyAt('08:15')
            ->withoutOverlapping();

        // First-party analytics: retention policy + destruction of past
        // visitor-hash salts (shortly after midnight, Morocco time), and the
        // monthly refresh of the local GeoIP database (DB-IP publishes early
        // in the month; a failed download keeps the previous file).
        $schedule->command('analytics:prune')
            ->dailyAt('00:10')
            ->timezone(config('analytics.timezone'))
            ->withoutOverlapping();
        $schedule->command('analytics:geoip-update')
            ->monthlyOn(5, '03:30')
            ->withoutOverlapping();

        // AI blog automation: one article per day at most (switches in the
        // backoffice; disabled by default). The command only reserves the
        // day's slot and queues the job, so it returns quickly; the unique
        // slot_key in blog_generations makes any second run a no-op.
        $schedule->command('blog:generate-daily')
            ->dailyAt(config('blog_automation.schedule_time'))
            ->timezone(config('blog_automation.timezone'))
            ->withoutOverlapping(60)
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
