<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'slug'   => $this->slug,
            'status' => $this->status,

            'title'           => $this->getTranslations('title'),
            'excerpt'         => $this->getTranslations('excerpt'),
            'content_html'    => $this->getTranslations('content_html'),
            'hero_image_alt'  => $this->getTranslations('hero_image_alt'),
            'hero_caption'    => $this->getTranslations('hero_caption'),
            'seo_title'       => $this->getTranslations('seo_title'),
            'seo_description' => $this->getTranslations('seo_description'),
            'toc'             => $this->getTranslations('toc'),

            'author'          => $this->author,
            'category'        => $this->category,
            'audience_tag'    => $this->audience_tag,
            'cover_url'       => $this->cover_url,
            'hero_image_url'  => $this->hero_image_url,
            'og_image_url'    => $this->og_image_url,
            'canonical_url'   => $this->canonical_url,
            'reading_time'    => $this->reading_time,
            'trending_score'  => $this->trending_score,
            'featured'        => $this->featured,
            'published_at'    => $this->published_at?->toIso8601String(),

            'faq' => $this->faqItems->map(fn ($item) => [
                'id'       => $item->id,
                'question' => $item->getTranslations('question'),
                'answer'   => $item->getTranslations('answer'),
            ]),

            'related_posts' => $this->relatedPosts->map(fn ($p) => [
                'id'    => $p->id,
                'slug'  => $p->slug,
                'title' => $p->getTranslation('title', 'pt'),
            ]),
        ];
    }
}
