<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogPost extends Model
{
    use HasUuids;

    protected $table = 'blog_posts';

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'content_html',
        'cover_url',
        'hero_image_url',
        'hero_image_alt',
        'hero_caption',
        'author',
        'category',
        'audience_tag',
        'reading_time',
        'language',
        'trending_score',
        'featured',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'og_image_url',
        'canonical_url',
    ];

    protected $casts = [
        'featured'       => 'boolean',
        'published_at'   => 'datetime',
        'trending_score' => 'integer',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeForLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function tocItems(): HasMany
    {
        return $this->hasMany(BlogTocItem::class, 'post_id')
                    ->orderBy('order_index');
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(BlogFaqItem::class, 'post_id')
                    ->orderBy('order_index');
    }

    public function relatedPosts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'blog_related_posts',
            'post_id',
            'related_post_id'
        );
    }
}
