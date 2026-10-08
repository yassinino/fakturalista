<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Central feedback entry - written by App\Services\Feedback\FeedbackService
 * (public site + tenant app), managed in the Filament backoffice
 * (App\Filament\Resources\FeedbackResource).
 *
 * Pinned to the central connection so writes made inside tenant context
 * (DatabaseTenancyBootstrapper swaps the default connection) still land here.
 */
class Feedback extends Model
{
    protected $connection = 'mysql';

    protected $table = 'feedback';

    public const TYPES    = ['suggestion', 'bug', 'improvement', 'other'];
    public const STATUSES = ['new', 'in_progress', 'resolved', 'closed'];

    public const SOURCE_PUBLIC = 'public';
    public const SOURCE_TENANT = 'tenant';

    /** Private, central disk (config/filesystems.php) - never tenant-suffixed. */
    public const DISK = 'feedback';

    // Backoffice labels (the backoffice is French-only, like the admin mails).
    public const TYPE_LABELS = [
        'suggestion'  => 'Suggestion',
        'bug'         => 'Bug',
        'improvement' => 'Amélioration',
        'other'       => 'Autre',
    ];

    public const STATUS_LABELS = [
        'new'         => 'Nouveau',
        'in_progress' => 'En cours',
        'resolved'    => 'Résolu',
        'closed'      => 'Fermé',
    ];

    public const SOURCE_LABELS = [
        self::SOURCE_PUBLIC => 'Visiteur',
        self::SOURCE_TENANT => 'Tenant',
    ];

    protected $fillable = [
        'type', 'status', 'source', 'message', 'email',
        'tenant_id', 'user_id', 'company_name', 'user_name',
        'page_url', 'locale', 'ip_address', 'user_agent',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
        'admin_notes',
    ];

    protected $casts = [
        'user_id'         => 'integer',
        'attachment_size' => 'integer',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $feedback) {
            if ($feedback->attachment_path) {
                Storage::disk(self::DISK)->delete($feedback->attachment_path);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(FeedbackReply::class)->orderBy('created_at');
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
