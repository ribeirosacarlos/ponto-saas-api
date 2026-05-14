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
        $langs = ['pt', 'es', 'en'];

        $translatable = [];
        foreach ($langs as $lang) {
            $translatable["title.$lang"]           = ['required', 'string', 'max:255'];
            $translatable["excerpt.$lang"]         = ['required', 'string', 'max:500'];
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
            'slug'               => ['required', 'string', 'max:255', 'unique:blog_posts,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'author'             => ['required', 'string', 'max:255'],
            'category'           => ['required', 'string', 'max:100'],
            'status'             => ['required', 'in:draft,published,archived'],
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

    public function messages(): array
    {
        return [
            'slug.regex'  => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Este slug já está em uso por outro post.',
        ];
    }
}
