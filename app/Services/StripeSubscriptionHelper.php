<?php

namespace App\Services;

/**
 * Stripe moved `current_period_end`/`current_period_start` off the
 * Subscription object onto each subscription item as of the account's
 * pinned API version (confirmed live against this project's own Stripe
 * test account - accessing `$subscription->current_period_end` directly
 * now returns null, which every call site here used to feed straight
 * into Carbon::createFromTimestamp(), silently writing 1970-01-01 into
 * `current_period_ends_at` - a real, launch-blocking billing-date bug,
 * not a hypothetical one).
 *
 * Single place to read it correctly, with a fallback to the old
 * top-level field for any account still pinned to a pre-2025 API
 * version where it existed there.
 */
class StripeSubscriptionHelper
{
    public static function currentPeriodEnd(object $stripeSubscription): ?int
    {
        return $stripeSubscription->current_period_end
            ?? $stripeSubscription->items->data[0]->current_period_end
            ?? null;
    }
}
