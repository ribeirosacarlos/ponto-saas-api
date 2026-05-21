<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostDetailResource;
use App\Http\Resources\BlogPostListResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'language' => ['nullable', 'in:pt,es,en'],
            'category' => ['nullable', 'string'],
            'featured' => ['nullable', 'boolean'],
            'search'   => ['nullable', 'string', 'max:100'],
            'sort'     => ['nullable', 'in:published_at_desc,trending_score_desc'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $language = $request->input('language', 'pt');
        app()->setLocale($language);

        $query = BlogPost::published()
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->featured, fn ($q) => $q->where('featured', true))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereRaw("title->>'pt' ILIKE ?", ["%{$search}%"])
                          ->orWhereRaw("title->>'es' ILIKE ?", ["%{$search}%"])
                          ->orWhereRaw("title->>'en' ILIKE ?", ["%{$search}%"]);
                });
            });

        $sort = $request->input('sort', 'published_at_desc');
        match ($sort) {
            'trending_score_desc' => $query->orderByDesc('trending_score'),
            default               => $query->orderByDesc('published_at'),
        };

        $posts = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => BlogPostListResource::collection($posts->items()),
            'meta' => [
                'total'     => $posts->total(),
                'page'      => $posts->currentPage(),
                'per_page'  => $posts->perPage(),
                'last_page' => $posts->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $language = $request->input('language', 'pt');
        app()->setLocale($language);

        $post = BlogPost::published()
            ->with(['faqItems', 'relatedPosts'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['data' => new BlogPostDetailResource($post)]);
    }

    public function categories(): JsonResponse
    {
        $categories = BlogCategory::orderBy('sort_order')->get();

        return response()->json([
            'data' => $categories->map(fn ($c) => [
                'key'      => $c->key,
                'label_pt' => $c->label_pt,
                'label_es' => $c->label_es,
                'label_en' => $c->label_en,
            ]),
        ]);
    }
}
