<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content_html' => $this->content_html,
            'cover_url' => $this->cover_url ?: config('blog.default_image_url'),
            'hero_image_url' => $this->hero_image_url ?: config('blog.default_image_url'),
            'hero_image_alt' => $this->hero_image_alt,
            'hero_caption' => $this->hero_caption,
            'author' => $this->author,
            'category' => $this->category,
            'audience_tag' => $this->audience_tag,
            'reading_time' => $this->reading_time,
            'featured' => $this->featured,
            'trending_score' => $this->trending_score,
            'published_at' => $this->published_at?->toIso8601String(),

            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'og_image_url' => $this->og_image_url ?: config('blog.default_image_url'),
            'canonical_url' => $this->canonical_url,

            'toc' => $this->toc ?? [],

            'faq' => $this->faqItems->map(fn ($item) => [
                'question' => $item->question,
                'answer' => $item->answer,
            ]),

            'related_posts' => $this->relatedPosts->map(fn ($post) => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'category' => $post->category,
                'audience_tag' => $post->audience_tag,
                'author' => $post->author,
                'published_at' => $post->published_at?->toIso8601String(),
                'reading_time' => $post->reading_time,
                'cover_url' => $post->cover_url ?: config('blog.default_image_url'),
            ]),
        ];
    }
}
