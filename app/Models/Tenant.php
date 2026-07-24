<?php

namespace App\Models;

use Carbon\Carbon;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'subscription_status',
        'trial_ends_at',
        'owner_name',
        'owner_email',
        'company_name',
        'company_email',
        'company_phone',
        'country',
        'timezone',
        'currency',
        'language',
        'status',
    ];

    protected $casts = [
        'data'          => 'array',
        'trial_ends_at' => 'datetime',
    ];

    // Tell VirtualColumn these are real DB columns, not JSON-encoded in `data`.
    // subscription_status and trial_ends_at are intentionally left out -
    // they were added before this override existed and live in the JSON column.
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'owner_name',
            'owner_email',
            'company_name',
            'company_email',
            'company_phone',
            'country',
            'timezone',
            'currency',
            'language',
            'status',
        ];
    }

    // ── Billing helpers ───────────────────────────────────────

    public function isOnActiveTrial(): bool
    {
        return $this->subscription_status === 'trialing'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active';
    }

    public function canAccessApp(): bool
    {
        if ($this->status === 'suspended') {
            return false;
        }
        return $this->isOnActiveTrial() || $this->hasActiveSubscription();
    }

    public function isReadOnly(): bool
    {
        if ($this->subscription_status === null) {
            return false;
        }
        return !$this->canAccessApp();
    }

    public function trialDaysLeft(): ?int
    {
        if (!$this->isOnActiveTrial()) {
            return null;
        }
        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    public function showTrialBanner(): bool
    {
        $days = $this->trialDaysLeft();
        return $days !== null && $days <= 7;
    }

    public function syncSubscriptionStatus(string $stripeStatus): void
    {
        $map = [
            'active'   => 'active',
            'trialing' => 'trialing',
            'past_due' => 'active',
            'canceled' => 'canceled',
            'unpaid'   => 'expired',
            'incomplete_expired' => 'expired',
        ];

        $newStatus = $map[$stripeStatus] ?? 'expired';
        $this->update(['subscription_status' => $newStatus]);
    }
}
