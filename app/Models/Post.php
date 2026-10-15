<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Post extends Model
{
    // Marketing content lives in the central database (never a tenant one).
    protected $connection = 'mysql';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'featured_image',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
        'ai_generated',
        'primary_keyword',
        'secondary_keywords',
        'target_country',
    ];

    protected $casts = [
        'published_at'       => 'datetime',
        'ai_generated'       => 'boolean',
        'secondary_keywords' => 'array',
    ];

    // Auto-generate slug from title if not provided
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });

        // An AI draft published from the article form (instead of the
        // automation's "approve" action) closes its generation too.
        static::updated(function (self $post) {
            if ($post->ai_generated && $post->wasChanged('status') && $post->status === 'published') {
                BlogGeneration::where('post_id', $post->id)
                    ->where('status', BlogGeneration::STATUS_DRAFT)
                    ->update([
                        'status'      => BlogGeneration::STATUS_PUBLISHED,
                        'reviewed_at' => now(),
                        'reviewed_by' => auth('admin')->id(),
                    ]);
            }
        });
    }

    public function generation(): HasOne
    {
        return $this->hasOne(BlogGeneration::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function getMetaTitleAttribute($value): string
    {
        return $value ?: $this->title;
    }

    public function getMetaDescriptionAttribute($value): string
    {
        return $value ?: Str::limit(strip_tags($this->excerpt ?? $this->body), 160);
    }
}
