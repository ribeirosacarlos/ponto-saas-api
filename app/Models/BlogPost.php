<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class BlogPost extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'blog_posts';

    public array $translatable = [
        'title',
        'excerpt',
        'content_html',
        'hero_image_alt',
        'hero_caption',
        'seo_title',
        'seo_description',
        'toc',
    ];

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'content_html',
        'toc',
        'cover_url',
        'hero_image_url',
        'hero_image_alt',
        'hero_caption',
        'author',
        'category',
        'audience_tag',
        'reading_time',
        'trending_score',
        'featured',
        'status',
        'source',
        'published_at',
        'seo_title',
        'seo_description',
        'og_image_url',
        'canonical_url',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
        'trending_score' => 'integer',
        'toc' => 'array',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(BlogFaqItem::class, 'post_id')->orderBy('order_index');
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
