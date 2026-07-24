<?php

namespace App\Models;

use App\Traits\HasJsonTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasJsonTranslations;

    protected $connection = 'mysql';
    protected $guarded    = [];

    protected $casts = [
        'active'        => 'boolean',
        'is_featured'   => 'boolean',
        'monthly_price' => 'integer',
        'yearly_price'  => 'integer',
        'sort_order'    => 'integer',
        'trial_days'    => 'integer',
        // Legacy column - NOT casting 'features' here: it collides with the
        // BelongsToMany relationship of the same name. Eloquent checks $casts
        // before getRelationValue(), so a cast entry wins and returns null
        // instead of the eager-loaded Collection.
        'amount'        => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    public function marketingItems(): HasMany
    {
        return $this->hasMany(PlanMarketingItem::class)->orderBy('sort_order');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Check if this plan includes a specific feature by slug.
     * Requires the 'features' relation to be eager-loaded.
     */
    public function hasFeature(string $slug): bool
    {
        return $this->features->contains('slug', $slug);
    }

    /**
     * Get the numeric limit for a resource.
     * null  → unlimited
     * int   → hard cap
     * Requires the 'limits' relation to be eager-loaded.
     */
    public function getLimit(string $resource): ?int
    {
        $row = $this->limits->firstWhere('resource', $resource);
        if ($row === null) {
            return null; // no row = unlimited
        }
        return $row->value;
    }

    /**
     * Formatted monthly price in major units (e.g. 490 → "4,90").
     */
    public function formattedPrice(?string $interval = 'monthly'): string
    {
        $price = $interval === 'yearly' ? $this->yearly_price : $this->monthly_price;
        return number_format(($price ?? 0) / 100, 2, ',', '');
    }

    /** @deprecated use formattedPrice() */
    public function priceFormatted(): string
    {
        return $this->formattedPrice();
    }
}
