<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostDetailResource;
use App\Http\Resources\BlogPostListResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicBlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'featured' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:published_at_desc,trending_score_desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $lang = $this->resolveLang($request);
        app()->setLocale($lang);

        $query = BlogPost::published()
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->featured, fn ($q) => $q->where('featured', true))
            ->when($request->input('q'), function ($q, $term) use ($lang) {
                $q->where(function ($inner) use ($term, $lang) {
                    if (DB::getDriverName() === 'pgsql') {
                        $inner->whereRaw('title->>? ILIKE ?', [$lang, "%{$term}%"])
                            ->orWhereRaw('excerpt->>? ILIKE ?', [$lang, "%{$term}%"]);
                    } else {
                        $path = '$.'.$lang;
                        $inner->whereRaw('json_extract(title, ?) LIKE ?', [$path, "%{$term}%"])
                            ->orWhereRaw('json_extract(excerpt, ?) LIKE ?', [$path, "%{$term}%"]);
                    }
                });
            });

        $sort = $request->input('sort', 'published_at_desc');
        match ($sort) {
            'trending_score_desc' => $query->orderByDesc('trending_score'),
            default => $query->orderByDesc('published_at'),
        };

        $posts = $query->paginate($request->input('per_page', 9));

        return response()->json([
            'data' => BlogPostListResource::collection($posts->items()),
            'meta' => [
                'total' => $posts->total(),
                'page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $lang = $this->resolveLang($request);
        app()->setLocale($lang);

        $post = BlogPost::published()
            ->with(['faqItems', 'relatedPosts'])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            abort(404, 'Post not found');
        }

        return response()->json(['data' => new BlogPostDetailResource($post)]);
    }

    private function resolveLang(Request $request): string
    {
        $lang = $request->input('lang');

        return in_array($lang, ['pt', 'es', 'en'], true) ? $lang : 'es';
    }

    public function categories(): JsonResponse
    {
        $categories = BlogCategory::orderBy('sort_order')->get();

        return response()->json([
            'data' => $categories->map(fn ($c) => [
                'key' => $c->key,
                'label_pt' => $c->label_pt,
                'label_es' => $c->label_es,
                'label_en' => $c->label_en,
            ]),
        ]);
    }
}
