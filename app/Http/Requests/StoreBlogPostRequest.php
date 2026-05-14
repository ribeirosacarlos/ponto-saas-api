<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'              => ['required', 'string', 'max:255'],
            'slug'               => ['required', 'string', 'max:255', 'unique:blog_posts,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt'            => ['required', 'string', 'max:500'],
            'author'             => ['required', 'string', 'max:255'],
            'category'           => ['required', 'string', 'max:100'],
            'language'           => ['required', 'in:pt,es,en'],
            'status'             => ['required', 'in:draft,published,archived'],

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
            'og_image_url'       => ['nullable', 'url', 'max:2048'],
            'canonical_url'      => ['nullable', 'url', 'max:2048'],
            'seo_title'          => ['nullable', 'string', 'max:255'],
            'seo_description'    => ['nullable', 'string', 'max:500'],

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

    public function messages(): array
    {
        return [
            'slug.regex'  => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Este slug já está em uso por outro post.',
        ];
    }
}
