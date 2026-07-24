<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for plan-limit enforcement.
 *
 * All methods run inside a tenant context (tenant DB is already
 * initialized by Stancl Tenancy before any API request reaches here).
 * Plan / Subscription models carry $connection = 'mysql' so they
 * always hit the central DB regardless of the active tenant connection.
 */
class PlanLimitService
{
    // ── Plan resolution ───────────────────────────────────────────────

    public function currentPlan(): ?Plan
    {
        $tenantId = tenant('id');

        $subscription = Subscription::on('mysql')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trialing'])
            ->with('plan')
            ->latest()
            ->first();

        return $subscription?->plan;
    }

    /**
     * Returns the numeric limit value for the given column.
     * null  → unlimited (Business plan)
     * 0     → no plan / no active subscription
     * int   → the hard cap
     */
    public function getLimit(string $limitColumn): ?int
    {
        $plan = $this->currentPlan();

        if (!$plan) {
            return 0;
        }

        // Cast returns null for nullable columns when the value is NULL
        return $plan->$limitColumn;
    }

    // ── Invoice limits ────────────────────────────────────────────────

    public function canCreateInvoice(): bool
    {
        $limit = $this->getLimit('max_invoices_per_month');

        if ($limit === null) {
            return true; // unlimited
        }

        return $this->invoicesThisMonth() < $limit;
    }

    public function invoicesThisMonth(): int
    {
        return Invoice::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();
    }

    public function remainingInvoices(): ?int
    {
        $limit = $this->getLimit('max_invoices_per_month');

        if ($limit === null) {
            return null; // unlimited
        }

        return max(0, $limit - $this->invoicesThisMonth());
    }

    public function invoiceQuotaResetsAt(): string
    {
        return Carbon::now()->startOfMonth()->addMonth()->toDateString();
    }

    // ── Customer limits ───────────────────────────────────────────────

    public function canCreateCustomer(): bool
    {
        $limit = $this->getLimit('max_customers');

        if ($limit === null) {
            return true;
        }

        return $this->totalCustomers() < $limit;
    }

    public function totalCustomers(): int
    {
        return Customer::count();
    }

    public function remainingCustomers(): ?int
    {
        $limit = $this->getLimit('max_customers');

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->totalCustomers());
    }

    // ── User limits ───────────────────────────────────────────────────

    public function canInviteUser(): bool
    {
        $limit = $this->getLimit('max_users');

        if ($limit === null) {
            return true;
        }

        return $this->totalUsers() < $limit;
    }

    public function totalUsers(): int
    {
        return User::count();
    }

    public function remainingUsers(): ?int
    {
        $limit = $this->getLimit('max_users');

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->totalUsers());
    }

    // ── Full usage summary (for the dashboard widget) ─────────────────

    public function getUsageSummary(): array
    {
        $plan = $this->currentPlan();

        return [
            'plan' => $plan ? [
                'name'   => $plan->name,
                'slug'   => $plan->slug,
                'amount' => $plan->amount,
            ] : null,

            'invoices' => [
                'used'      => $this->invoicesThisMonth(),
                'limit'     => $plan?->max_invoices_per_month,   // null = unlimited
                'remaining' => $this->remainingInvoices(),
                'resets_at' => $this->invoiceQuotaResetsAt(),
            ],

            'customers' => [
                'used'      => $this->totalCustomers(),
                'limit'     => $plan?->max_customers,
                'remaining' => $this->remainingCustomers(),
            ],

            'users' => [
                'used'      => $this->totalUsers(),
                'limit'     => $plan?->max_users,
                'remaining' => $this->remainingUsers(),
            ],
        ];
    }
}
