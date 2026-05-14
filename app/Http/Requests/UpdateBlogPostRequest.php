<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $postId = $this->route('id');

        return [
            'title'              => ['sometimes', 'required', 'string', 'max:255'],
            'slug'               => ['sometimes', 'required', 'string', 'max:255', Rule::unique('blog_posts', 'slug')->ignore($postId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt'            => ['sometimes', 'required', 'string', 'max:500'],
            'author'             => ['sometimes', 'required', 'string', 'max:255'],
            'category'           => ['sometimes', 'required', 'string', 'max:100'],
            'language'           => ['sometimes', 'required', 'in:pt,es,en'],
            'status'             => ['sometimes', 'required', 'in:draft,published,archived'],
            'content_html'       => ['nullable', 'string'],
            'cover_url'          => ['nullable', 'url', 'max:2048'],
            'hero_image_url'     => ['nullable', 'url', 'max:2048'],
            'hero_image_alt'     => ['nullable', 'string', 'max:255'],
            'hero_caption'       => ['nullable', 'string', 'max:500'],
            'audience_tag'       => ['nullable', 'string', 'max:100'],
            'reading_time'       => ['nullable', 'string', 'max:20'],
            'trending_score'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'featured'           => ['nullable', 'boolean'],
            'published_at'       => ['nullable', 'date'],
            'seo_title'          => ['nullable', 'string', 'max:255'],
            'seo_description'    => ['nullable', 'string', 'max:500'],
            'og_image_url'       => ['nullable', 'url', 'max:2048'],
            'canonical_url'      => ['nullable', 'url', 'max:2048'],
            'toc'                => ['nullable', 'array'],
            'toc.*.label'        => ['required_with:toc', 'string', 'max:255'],
            'toc.*.href'         => ['required_with:toc', 'string', 'max:255'],
            'faq'                => ['nullable', 'array'],
            'faq.*.question'     => ['required_with:faq', 'string'],
            'faq.*.answer'       => ['required_with:faq', 'string'],
            'related_post_ids'   => ['nullable', 'array'],
            'related_post_ids.*' => ['uuid', 'exists:blog_posts,id'],
        ];
    }
}
