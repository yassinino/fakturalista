<?php

namespace App\Console\Commands;

use App\Mail\InvoiceDueSoonReminderMail;
use App\Mail\InvoiceOverdueReminderMail;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantReminderLog;
use App\Services\NotificationPreferencesService;
use App\Services\TenantContextService;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Settings > Notifications - per-invoice due-date reminders ("Invoice due
 * soon" / "Invoice overdue"). Sibling of SendBillingRemindersCommand, kept
 * as its own command rather than folded into it: that command only ever
 * touches the central `mysql` connection (tenants/subscriptions), while
 * this one has to switch into each tenant's own database to read their
 * invoices and their Settings > Notifications preferences (CompanyProfile,
 * tenant DB) - a materially different operation. Both are wired into the
 * same Kernel::schedule() though, not a separate/second cron mechanism.
 *
 * Dedup reuses the existing TenantReminderLog table/unique-index pattern
 * (see the 2026_09_25_100003 migration): one row per (tenant, reminder
 * type, invoice, due date) actually sent. reminder_type is one of
 * invoice_due_{1,3,7}_days or invoice_overdue_{0,1,3,7}_days;
 * period_ends_at holds the invoice's due date, so a due-date change opens
 * a new dedup cycle for that invoice, exactly like a subscription renewal
 * opens a new cycle for subscription_* reminders.
 */
class SendInvoiceRemindersCommand extends Command
{
    protected $signature = 'invoices:send-reminders {--dry-run : Only print what would be sent, send nothing}';

    protected $description = 'Send per-invoice due-soon/overdue reminder emails to tenant owners, based on each tenant\'s Settings > Notifications preferences';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tenants = Tenant::on('mysql')->get();

        foreach ($tenants as $tenant) {
            if (empty($tenant->owner_email)) {
                continue;
            }

            try {
                $tenant->run(function () use ($tenant, $dryRun) {
                    $this->processTenant($tenant, $dryRun);
                });
            } catch (\Throwable $e) {
                $this->error("Tenant {$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info($dryRun ? 'Dry run complete - no emails sent.' : 'Invoice reminders complete.');

        return self::SUCCESS;
    }

    private function processTenant(Tenant $tenant, bool $dryRun): void
    {
        $prefsService = app(NotificationPreferencesService::class);
        $profile      = CompanyProfile::first();
        $prefs        = $prefsService->resolve($profile?->notification_preferences);

        if (!$prefs['email_notifications_enabled']) {
            return;
        }

        $today  = Carbon::today();
        $locale = app(TenantContextService::class)->locale();
        $domain = $tenant->domains->first()?->domain;
        $baseUrl = $domain ? 'https://' . $domain : url('/');

        if ($prefs['invoice_due_soon']) {
            foreach ($prefs['invoice_due_soon_days'] as $days) {
                $targetDate = $today->copy()->addDays($days)->toDateString();
                $type       = "invoice_due_{$days}_days";

                Invoice::where('status', Invoice::STATUS_ISSUED)
                    ->whereDate('expiration_date', $targetDate)
                    ->with('customer')
                    ->get()
                    ->each(function (Invoice $invoice) use ($tenant, $days, $type, $locale, $baseUrl, $dryRun) {
                        $this->sendOnce($tenant, $type, $invoice, $dryRun, function () use ($tenant, $invoice, $days, $locale, $baseUrl) {
                            $url = $baseUrl . '/admin/invoices/edit/' . $invoice->uuid;
                            Mail::send(
                                (new InvoiceDueSoonReminderMail($tenant, $invoice, $days, $url))->locale($locale)
                            );
                        });
                    });
            }
        }

        if ($prefs['invoice_overdue']) {
            foreach ($prefs['invoice_overdue_days'] as $days) {
                $targetDate = $today->copy()->subDays($days)->toDateString();
                $type       = "invoice_overdue_{$days}_days";

                Invoice::where('status', Invoice::STATUS_ISSUED)
                    ->whereDate('expiration_date', $targetDate)
                    ->with('customer')
                    ->get()
                    ->each(function (Invoice $invoice) use ($tenant, $days, $type, $locale, $baseUrl, $dryRun) {
                        $this->sendOnce($tenant, $type, $invoice, $dryRun, function () use ($tenant, $invoice, $days, $locale, $baseUrl) {
                            $url = $baseUrl . '/admin/invoices/edit/' . $invoice->uuid;
                            Mail::send(
                                (new InvoiceOverdueReminderMail($tenant, $invoice, $days, $url))->locale($locale)
                            );
                        });
                    });
            }
        }
    }

    /**
     * Same shape as SendBillingRemindersCommand::sendOnce() - exists-check
     * against the unique index, then a real row, per (tenant, type,
     * invoice, due date). $invoice->expiration_date is a plain date column
     * (no cast); normalizing to startOfDay() here keeps every write and
     * every future read of period_ends_at consistent regardless of that.
     */
    private function sendOnce(Tenant $tenant, string $type, Invoice $invoice, bool $dryRun, Closure $send): void
    {
        $dueDate = Carbon::parse($invoice->expiration_date)->startOfDay();

        $exists = TenantReminderLog::on('mysql')
            ->where('tenant_id', $tenant->id)
            ->where('reminder_type', $type)
            ->where('invoice_uuid', $invoice->uuid)
            ->where('period_ends_at', $dueDate)
            ->exists();

        if ($exists) {
            return;
        }

        $this->line(($dryRun ? '[dry-run] ' : '') . "{$type} -> tenant {$tenant->id}, invoice {$invoice->reference} (due {$dueDate->toDateString()})");

        if ($dryRun) {
            return;
        }

        $send();

        TenantReminderLog::on('mysql')->create([
            'tenant_id'      => $tenant->id,
            'invoice_uuid'   => $invoice->uuid,
            'reminder_type'  => $type,
            'period_ends_at' => $dueDate,
            'sent_at'        => now(),
        ]);
    }
}
