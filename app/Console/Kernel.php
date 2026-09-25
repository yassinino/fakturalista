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
