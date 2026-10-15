<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI blog generation run: execution log, topic history and daily slot
 * (unique slot_key "YYYY-MM-DD#n" = database-level idempotency). Written by
 * App\Services\BlogAutomation\BlogAutomationService only. Central database.
 */
class BlogGeneration extends Model
{
    protected $connection = 'mysql';

    protected $table = 'blog_generations';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_DRAFT     = 'draft';      // saved, awaiting human review
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_SKIPPED   = 'skipped';    // no unique topic available

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'En attente',
        self::STATUS_RUNNING   => 'En cours',
        self::STATUS_DRAFT     => 'À relire',
        self::STATUS_PUBLISHED => 'Publié',
        self::STATUS_REJECTED  => 'Rejeté',
        self::STATUS_FAILED    => 'Échec',
        self::STATUS_SKIPPED   => 'Ignoré',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING   => 'gray',
        self::STATUS_RUNNING   => 'info',
        self::STATUS_DRAFT     => 'warning',
        self::STATUS_PUBLISHED => 'success',
        self::STATUS_REJECTED  => 'gray',
        self::STATUS_FAILED    => 'danger',
        self::STATUS_SKIPPED   => 'gray',
    ];

    public const TRIGGER_LABELS = [
        'schedule' => 'Planifié',
        'manual'   => 'Manuel',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'run_date'            => 'date',
        'regulatory'          => 'boolean',
        'sources_verified'    => 'boolean',
        'validation_errors'   => 'array',
        'validation_warnings' => 'array',
        'draft_reasons'       => 'array',
        'sources'             => 'array',
        'ai_review'           => 'array',
        'estimated_cost_usd'  => 'decimal:5',
        'started_at'          => 'datetime',
        'finished_at'         => 'datetime',
        'notified_at'         => 'datetime',
        'failure_notified_at' => 'datetime',
        'reviewed_at'         => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }

    /** Rows that used a topic (topic history): everything that produced or tried content. */
    public function scopeUsedTopics(Builder $query): Builder
    {
        return $query->whereNotNull('topic_key')
            ->whereNotIn('status', [self::STATUS_FAILED, self::STATUS_SKIPPED]);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->post_id !== null;
    }

    public function countryLabel(): string
    {
        return config('blog_automation.country_labels.' . $this->country, (string) $this->country);
    }
}
