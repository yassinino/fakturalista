<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for plan limits, feature flags, and usage data.
 *
 * Runs inside a tenant context. Plan and Subscription models use $connection = 'mysql'
 * so they always hit the central DB regardless of the active tenant connection.
 *
 * Usage:
 *   app(PlanService::class)->hasFeature('stripe')
 *   app(PlanService::class)->canCreate('invoices_per_month')
 *   $plan->hasFeature('stripe')  // after loading with features relation
 */
class PlanService
{
    private ?Plan $cachedPlan = null;
    private bool  $resolved   = false;

    // ── Plan resolution ───────────────────────────────────────────────

    public function currentPlan(): ?Plan
    {
        if (!$this->resolved) {
            $this->resolved = true;

            $this->cachedPlan = Subscription::on('mysql')
                ->where('tenant_id', tenant('id'))
                ->whereIn('status', ['active', 'trialing'])
                ->with(['plan.features', 'plan.limits'])
                ->latest()
                ->first()?->plan;

            // Fallback for tenants provisioned before the subscription row was
            // created automatically. If the tenant is still in an active trial
            // according to the tenant record itself, resolve the starter plan so
            // limits are read from the DB instead of defaulting to 0 (blocked).
            if ($this->cachedPlan === null) {
                $tenant = tenancy()->tenant;
                $isActiveTenant = $tenant && in_array(
                    $tenant->subscription_status,
                    ['active', 'trialing', null],
                );
                if ($isActiveTenant) {
                    $this->cachedPlan = Plan::on('mysql')
                        ->where('slug', 'starter')
                        ->with(['features', 'limits'])
                        ->first();

                    if ($this->cachedPlan) {
                        Log::info('PlanService: no subscription row found, falling back to starter plan', [
                            'tenant_id' => tenant('id'),
                        ]);
                    }
                }
            }
        }

        return $this->cachedPlan;
    }

    // ── Feature flags ─────────────────────────────────────────────────

    public function hasFeature(string $slug): bool
    {
        $plan = $this->currentPlan();
        if (!$plan) {
            return false;
        }

        return $plan->hasFeature($slug);
    }

    // ── Limits ────────────────────────────────────────────────────────

    /**
     * Get the numeric limit for a resource.
     * null  → unlimited
     * 0     → no active plan (blocked)
     * int   → hard cap
     */
    public function getLimit(string $resource): ?int
    {
        $plan = $this->currentPlan();

        if (!$plan) {
            return 0;
        }

        return $plan->getLimit($resource); // null = unlimited, int = cap
    }

    /**
     * Check whether a new resource of the given type can be created.
     */
    public function canCreate(string $resource): bool
    {
        $limit = $this->getLimit($resource);

        if ($limit === null) {
            return true; // unlimited
        }

        if ($limit === 0) {
            return false; // no plan or explicitly blocked
        }

        return $this->count($resource) < $limit;
    }

    // ── Convenience methods ───────────────────────────────────────────

    public function canCreateInvoice(): bool  { return $this->canCreate('invoices_per_month'); }
    public function canCreateCustomer(): bool { return $this->canCreate('customers'); }
    public function canInviteUser(): bool     { return $this->canCreate('users'); }
    public function canCreateProduct(): bool  { return $this->canCreate('products'); }
    public function canCreateQuote(): bool    { return $this->canCreate('quotes'); }

    // ── Usage counts (run on tenant DB) ──────────────────────────────

    public function count(string $resource): int
    {
        return match ($resource) {
            'invoices_per_month' => $this->invoicesThisMonth(),
            'customers'          => $this->totalCustomers(),
            'users'              => $this->totalUsers(),
            'products'           => $this->totalProducts(),
            'quotes'             => $this->totalQuotes(),
            default              => 0,
        };
    }

    public function invoicesThisMonth(): int
    {
        return Invoice::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();
    }

    public function totalCustomers(): int { return Customer::count(); }
    public function totalUsers(): int     { return User::count(); }
    public function totalProducts(): int  { return Item::count(); }
    public function totalQuotes(): int    { return Quote::count(); }

    public function remaining(string $resource): ?int
    {
        $limit = $this->getLimit($resource);
        if ($limit === null) {
            return null; // unlimited
        }
        return max(0, $limit - $this->count($resource));
    }

    public function invoiceQuotaResetsAt(): string
    {
        return Carbon::now()->startOfMonth()->addMonth()->toDateString();
    }

    // ── Full usage summary (for API + dashboard) ──────────────────────

    public function getUsageSummary(): array
    {
        $plan = $this->currentPlan();

        return [
            'plan' => $plan ? [
                'name'          => $plan->translate('name'),
                'slug'          => $plan->slug,
                'monthly_price' => $plan->monthly_price,
            ] : null,

            'invoices' => [
                'used'      => $this->invoicesThisMonth(),
                'limit'     => $plan ? $plan->getLimit('invoices_per_month') : 0,
                'remaining' => $this->remaining('invoices_per_month'),
                'resets_at' => $this->invoiceQuotaResetsAt(),
            ],

            'customers' => [
                'used'      => $this->totalCustomers(),
                'limit'     => $plan ? $plan->getLimit('customers') : 0,
                'remaining' => $this->remaining('customers'),
            ],

            'users' => [
                'used'      => $this->totalUsers(),
                'limit'     => $plan ? $plan->getLimit('users') : 0,
                'remaining' => $this->remaining('users'),
            ],

            'products' => [
                'used'      => $this->totalProducts(),
                'limit'     => $plan ? $plan->getLimit('products') : 0,
                'remaining' => $this->remaining('products'),
            ],

            'quotes' => [
                'used'      => $this->totalQuotes(),
                'limit'     => $plan ? $plan->getLimit('quotes') : 0,
                'remaining' => $this->remaining('quotes'),
            ],
        ];
    }
}
