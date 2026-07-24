<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanMarketingItem extends Model
{
    protected $connection = 'mysql';
    protected $guarded    = [];

    protected $casts = [
        'is_highlighted' => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get localized text. Falls back to fr then en.
     */
    public function text(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        return $this->{"text_{$locale}"} ?? $this->text_fr ?? $this->text_en ?? '';
    }
}
