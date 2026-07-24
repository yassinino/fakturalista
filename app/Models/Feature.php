<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $connection = 'mysql';
    protected $guarded    = [];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features');
    }

    /**
     * Get localized name. Falls back to fr then en.
     */
    public function name(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        return $this->{"name_{$locale}"} ?? $this->name_fr ?? $this->name_en ?? $this->slug;
    }
}
