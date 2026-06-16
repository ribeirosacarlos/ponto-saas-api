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
        $langs  = ['pt', 'es', 'en'];

        $translatable = [];
        foreach ($langs as $lang) {
            $translatable["title.$lang"]           = ['sometimes', 'required', 'string', 'max:255'];
            $translatable["excerpt.$lang"]         = ['sometimes', 'required', 'string', 'max:500'];
            $translatable["content_html.$lang"]    = ['nullable', 'string'];
            $translatable["hero_image_alt.$lang"]  = ['nullable', 'string', 'max:255'];
            $translatable["hero_caption.$lang"]    = ['nullable', 'string', 'max:500'];
            $translatable["seo_title.$lang"]       = ['nullable', 'string', 'max:255'];
            $translatable["seo_description.$lang"] = ['nullable', 'string', 'max:500'];
            $translatable["toc.$lang"]             = ['nullable', 'array'];
            $translatable["toc.$lang.*.label"]     = ['required_with:toc.'.$lang, 'string', 'max:255'];
            $translatable["toc.$lang.*.href"]      = ['required_with:toc.'.$lang, 'string', 'max:255'];
        }

        return array_merge($translatable, [
            'slug'               => ['sometimes', 'required', 'string', 'max:255', Rule::unique('blog_posts', 'slug')->ignore($postId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'author'             => ['sometimes', 'required', 'string', 'max:255'],
            'category'           => ['sometimes', 'required', 'string', 'max:100'],
            'status'             => ['sometimes', 'required', 'in:draft,published,archived'],
            'cover_url'          => ['nullable', 'url', 'max:2048'],
            'hero_image_url'     => ['nullable', 'url', 'max:2048'],
            'audience_tag'       => ['nullable', 'string', 'max:100'],
            'reading_time'       => ['nullable', 'string', 'max:20'],
            'trending_score'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'featured'           => ['nullable', 'boolean'],
            'published_at'       => ['nullable', 'date'],
            'og_image_url'       => ['nullable', 'url', 'max:2048'],
            'canonical_url'      => ['nullable', 'url', 'max:2048'],
            'faq'                => ['nullable', 'array'],
            'faq.*.question'     => ['required_with:faq', 'array'],
            'faq.*.answer'       => ['required_with:faq', 'array'],
            'faq.*.question.pt'  => ['required_with:faq', 'string'],
            'faq.*.question.es'  => ['required_with:faq', 'string'],
            'faq.*.question.en'  => ['required_with:faq', 'string'],
            'faq.*.answer.pt'    => ['required_with:faq', 'string'],
            'faq.*.answer.es'    => ['required_with:faq', 'string'],
            'faq.*.answer.en'    => ['required_with:faq', 'string'],
            'related_post_ids'   => ['nullable', 'array'],
            'related_post_ids.*' => ['uuid', 'exists:blog_posts,id'],
        ]);
    }
}
