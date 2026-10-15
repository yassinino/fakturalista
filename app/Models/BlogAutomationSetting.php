<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single settings row of the AI blog automation (Filament page
 * "Automatisation blog IA"). Central database only. Defaults are the safe
 * ones: disabled, draft-only, manual approval required.
 */
class BlogAutomationSetting extends Model
{
    protected $connection = 'mysql';

    protected $table = 'blog_automation_settings';

    protected $fillable = [
        'enabled',
        'auto_publish',
        'require_manual_approval',
        'allow_regulatory_autopublish',
        'ai_review',
        'max_per_day',
        'target_countries',
        'language',
        'excluded_topics',
        'notification_email',
        'last_run_at',
        'last_run_status',
        'last_run_message',
    ];

    protected $casts = [
        'enabled'                      => 'boolean',
        'auto_publish'                 => 'boolean',
        'require_manual_approval'      => 'boolean',
        'allow_regulatory_autopublish' => 'boolean',
        'ai_review'                    => 'boolean',
        'max_per_day'                  => 'integer',
        'target_countries'             => 'array',
        'last_run_at'                  => 'datetime',
    ];

    protected $attributes = [
        'enabled'                      => false,
        'auto_publish'                 => false,
        'require_manual_approval'      => true,
        'allow_regulatory_autopublish' => false,
        'ai_review'                    => true,
        'max_per_day'                  => 1,
        'language'                     => 'fr',
    ];

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create(['target_countries' => array_keys(config('blog_automation.country_weights'))]);
    }

    /** Countries the selector may pick (always a subset of the weighted ones). */
    public function countries(): array
    {
        $known = array_keys(config('blog_automation.country_weights'));
        $chosen = array_values(array_intersect($known, (array) ($this->target_countries ?: $known)));

        return $chosen ?: $known;
    }

    /** Admin-entered exclusions, one per line, lowercased. */
    public function exclusions(): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => mb_strtolower(trim($line)),
            preg_split('/\R/', (string) $this->excluded_topics)
        )));
    }

    /** Whether a valid article may go live without a human. */
    public function publishesAutomatically(): bool
    {
        return $this->auto_publish && ! $this->require_manual_approval;
    }

    /** Never above the configured cap (one article per day). */
    public function dailyLimit(): int
    {
        return min((int) config('blog_automation.max_per_day_cap', 1), max(1, (int) $this->max_per_day));
    }

    public function notificationEmail(): string
    {
        return $this->notification_email ?: (string) config('fakturalista.admin_email');
    }

    public function recordRun(string $status, string $message): void
    {
        $this->forceFill([
            'last_run_at'      => now(),
            'last_run_status'  => $status,
            'last_run_message' => mb_substr($message, 0, 500),
        ])->save();
    }
}
