<?php

namespace App\Services;

/**
 * Settings > Notifications preferences - stored as one JSON blob on the
 * tenant's CompanyProfile (see the 2026_09_25_100002 migration), resolved
 * here against sensible defaults so every existing tenant (null column)
 * behaves as if every optional notification were already turned on.
 *
 * Critical/required notifications (trial-ending-without-attention-needed
 * aside, mainly the subscription-payment-issue reminder) are NOT covered
 * by any key here - App\Console\Commands\SendBillingRemindersCommand
 * sends those unconditionally, exactly as it already did before this
 * service existed. This service only gates the notifications that are
 * genuinely optional.
 */
class NotificationPreferencesService
{
    public const DEFAULTS = [
        'email_notifications_enabled' => true,
        'invoice_due_soon'            => true,
        'invoice_due_soon_days'       => [3],
        'invoice_overdue'             => true,
        'invoice_overdue_days'        => [0, 3],
        'invoice_paid'                => true,
        'trial_ending'                => true,
        'quote_converted'             => true,
    ];

    // Reminder-timing choices exposed in the UI - kept here so the
    // controller can validate against the exact same list the UI offers.
    public const DUE_SOON_DAYS = [1, 3, 7];
    public const OVERDUE_DAYS  = [0, 1, 3, 7];

    /**
     * Merge the given tenant's stored preferences (may be null/partial -
     * an existing tenant, or one who has only ever changed one toggle)
     * over the defaults above.
     */
    public function resolve(?array $stored): array
    {
        $prefs = array_merge(self::DEFAULTS, array_filter(
            $stored ?? [],
            fn ($key) => array_key_exists($key, self::DEFAULTS),
            ARRAY_FILTER_USE_KEY
        ));

        $prefs['invoice_due_soon_days'] = $this->sanitizeDays($prefs['invoice_due_soon_days'] ?? self::DEFAULTS['invoice_due_soon_days'], self::DUE_SOON_DAYS);
        $prefs['invoice_overdue_days']  = $this->sanitizeDays($prefs['invoice_overdue_days'] ?? self::DEFAULTS['invoice_overdue_days'], self::OVERDUE_DAYS);

        foreach (['email_notifications_enabled', 'invoice_due_soon', 'invoice_overdue', 'invoice_paid', 'trial_ending', 'quote_converted'] as $flag) {
            $prefs[$flag] = (bool) ($prefs[$flag] ?? true);
        }

        return $prefs;
    }

    /**
     * Is this optional notification currently allowed to send? False for
     * every optional type as soon as the master switch is off - the
     * master switch never touches the required/critical ones because
     * those never call this method in the first place.
     */
    public function isEnabled(?array $stored, string $key): bool
    {
        $prefs = $this->resolve($stored);

        return $prefs['email_notifications_enabled'] && (bool) ($prefs[$key] ?? false);
    }

    private function sanitizeDays($days, array $allowed): array
    {
        if (!is_array($days)) {
            return [];
        }

        $days = array_values(array_unique(array_map('intval', $days)));

        return array_values(array_intersect($days, $allowed));
    }
}
