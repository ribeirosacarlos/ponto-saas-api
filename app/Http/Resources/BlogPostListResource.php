<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_url' => $this->cover_url ?: config('blog.default_image_url'),
            'category' => $this->category,
            'audience_tag' => $this->audience_tag,
            'author' => $this->author,
            'reading_time' => $this->reading_time,
            'published_at' => $this->published_at?->toIso8601String(),
            'featured' => $this->featured,
            'trending_score' => $this->trending_score,
        ];
    }
}
