<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionEndingReminderMail;
use App\Mail\TrialEndingReminderMail;
use App\Models\CompanyProfile;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantReminderLog;
use App\Services\NotificationPreferencesService;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Daily reminder sweep for two situations, each with a "3 days left" and a
 * "1 day left" email (TenantReminderLog dedup, unique on tenant_id +
 * reminder_type + period_ends_at):
 *
 *  - Free trial ending soon (tenants.subscription_status = 'trialing') -
 *    sent at most ONCE EVER per tenant (a trial has no cycle to repeat, so
 *    these rows always carry a null period_ends_at).
 *  - A subscription approaching its current_period_ends_at without a
 *    healthy automatic renewal in progress (status past_due/incomplete,
 *    or active but Stripe's cancel_at_period_end is set - see
 *    App\Models\Subscription for why that flag, not status alone, is the
 *    only reliable signal) - sent at most once PER BILLING CYCLE: if the
 *    tenant renews and a later cycle again approaches expiration without
 *    renewing, the reminder fires again, keyed off the new
 *    current_period_ends_at.
 *
 * Trial and subscription data both live on the central `mysql` connection
 * (tenants/subscriptions tables), so this never needs to switch into a
 * tenant's own database.
 */
class SendBillingRemindersCommand extends Command
{
    protected $signature = 'billing:send-reminders {--dry-run : Only print what would be sent, send nothing}';

    protected $description = 'Send trial-ending and subscription-ending reminder emails (3 days / 1 day before), each at most once per tenant';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->sendTrialReminders($dryRun);
        $this->sendSubscriptionReminders($dryRun);

        $this->info($dryRun ? 'Dry run complete - no emails sent.' : 'Billing reminders complete.');

        return self::SUCCESS;
    }

    private function sendTrialReminders(bool $dryRun): void
    {
        $tenants = Tenant::on('mysql')
            ->where('subscription_status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->get();

        foreach ($tenants as $tenant) {
            // Reuses the app's own "days left" primitive (already accounts
            // for isOnActiveTrial()/isFuture()) rather than re-deriving it.
            $daysLeft = $tenant->trialDaysLeft();

            if (!in_array($daysLeft, [3, 1], true) || empty($tenant->owner_email)) {
                continue;
            }

            // Settings > Notifications - "Free trial ending" toggle. Only
            // this one optional trial/subscription reminder is gated by a
            // preference; the trial has no cycle so it only needs a quick
            // switch into the tenant's own DB to read CompanyProfile, not
            // a whole invoice-style per-item sweep.
            $enabled = $tenant->run(function () {
                $profile = CompanyProfile::first();

                return app(NotificationPreferencesService::class)->isEnabled($profile?->notification_preferences, 'trial_ending');
            });

            if (!$enabled) {
                continue;
            }

            $type = $daysLeft === 1 ? 'trial_1_day' : 'trial_3_days';

            $this->sendOnce($tenant, $type, $dryRun, function () use ($tenant, $daysLeft) {
                Mail::send(
                    (new TrialEndingReminderMail($tenant, $daysLeft, $this->subscribeUrl($tenant)))
                        ->locale($tenant->language ?: 'fr')
                );
            });
        }
    }

    private function sendSubscriptionReminders(bool $dryRun): void
    {
        $subscriptions = Subscription::on('mysql')
            ->whereNotNull('current_period_ends_at')
            ->whereIn('status', ['active', 'past_due', 'incomplete'])
            ->get();

        foreach ($subscriptions as $subscription) {
            // "Renewal/payment not going through" - not just any active
            // subscription approaching its normal, healthy renewal date.
            $needsAttention = in_array($subscription->status, ['past_due', 'incomplete'], true)
                || ($subscription->status === 'active' && ($subscription->raw['cancel_at_period_end'] ?? false) === true);

            if (!$needsAttention) {
                continue;
            }

            $daysLeft = (int) now()->diffInDays($subscription->current_period_ends_at, false);

            if (!in_array($daysLeft, [3, 1], true)) {
                continue;
            }

            $tenant = Tenant::on('mysql')->find($subscription->tenant_id);

            if (!$tenant || empty($tenant->owner_email)) {
                continue;
            }

            $type = $daysLeft === 1 ? 'subscription_1_day' : 'subscription_3_days';

            $this->sendOnce($tenant, $type, $dryRun, function () use ($tenant, $daysLeft) {
                Mail::send(
                    (new SubscriptionEndingReminderMail($tenant, $daysLeft, $this->subscribeUrl($tenant)))
                        ->locale($tenant->language ?: 'fr')
                );
            }, $subscription->current_period_ends_at);
        }
    }

    /**
     * Sends (or, on --dry-run, only reports) the reminder if - and only if -
     * no TenantReminderLog row for this exact (tenant, type, cycle) triple
     * exists yet, then records one. The unique index on the table is the
     * real guarantee against duplicates; this check is what avoids relying
     * on a race between "check" and "create" mattering in practice for a
     * single daily, single-process command.
     *
     * $periodEndsAt is null for trial reminders (once per tenant, ever) and
     * the subscription's current_period_ends_at for subscription reminders
     * (once per billing cycle - a later cycle with a new expiration date
     * is free to trigger the same reminder_type again).
     */
    private function sendOnce(Tenant $tenant, string $type, bool $dryRun, Closure $send, ?Carbon $periodEndsAt = null): void
    {
        $query = TenantReminderLog::on('mysql')
            ->where('tenant_id', $tenant->id)
            ->where('reminder_type', $type);

        $periodEndsAt === null ? $query->whereNull('period_ends_at') : $query->where('period_ends_at', $periodEndsAt);

        if ($query->exists()) {
            return;
        }

        $this->line(($dryRun ? '[dry-run] ' : '') . "{$type} -> tenant {$tenant->id} ({$tenant->owner_email})" . ($periodEndsAt ? " [cycle ending {$periodEndsAt}]" : ''));

        if ($dryRun) {
            return;
        }

        $send();

        TenantReminderLog::on('mysql')->create([
            'tenant_id'      => $tenant->id,
            'reminder_type'  => $type,
            'period_ends_at' => $periodEndsAt,
            'sent_at'        => now(),
        ]);
    }

    private function subscribeUrl(Tenant $tenant): string
    {
        $domain = $tenant->domains->first()?->domain;

        return $domain ? 'https://' . $domain . '/admin/subscription' : url('/admin/subscription');
    }
}
