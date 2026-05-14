<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlogPostRequest;
use App\Http\Requests\UpdateBlogPostRequest;
use App\Http\Resources\BlogPostDetailResource;
use App\Http\Resources\BlogPostListResource;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBlogPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = BlogPost::query()
            ->when($request->status,   fn ($q) => $q->where('status', $request->status))
            ->when($request->language, fn ($q) => $q->where('language', $request->language))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->orderByDesc('updated_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => BlogPostListResource::collection($posts->items()),
            'meta' => [
                'total'    => $posts->total(),
                'page'     => $posts->currentPage(),
                'per_page' => $posts->perPage(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $post = BlogPost::with(['tocItems', 'faqItems', 'relatedPosts'])
            ->findOrFail($id);

        return response()->json([
            'data' => new BlogPostDetailResource($post),
        ]);
    }

    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        $post = DB::transaction(function () use ($request) {
            $post = BlogPost::create($request->safe()->except(['toc', 'faq', 'related_post_ids']));

            $this->syncToc($post, $request->input('toc', []));
            $this->syncFaq($post, $request->input('faq', []));
            $this->syncRelated($post, $request->input('related_post_ids', []));

            return $post->load(['tocItems', 'faqItems', 'relatedPosts']);
        });

        return response()->json(['data' => new BlogPostDetailResource($post)], 201);
    }

    public function update(UpdateBlogPostRequest $request, string $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);

        DB::transaction(function () use ($post, $request) {
            $post->update($request->safe()->except(['toc', 'faq', 'related_post_ids']));

            if ($request->has('toc')) {
                $this->syncToc($post, $request->input('toc', []));
            }
            if ($request->has('faq')) {
                $this->syncFaq($post, $request->input('faq', []));
            }
            if ($request->has('related_post_ids')) {
                $this->syncRelated($post, $request->input('related_post_ids', []));
            }
        });

        return response()->json(['data' => new BlogPostDetailResource($post->fresh(['tocItems', 'faqItems', 'relatedPosts']))]);
    }

    public function destroy(string $id): JsonResponse
    {
        BlogPost::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function publish(string $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        $post->update([
            'status'       => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);

        return response()->json(['data' => new BlogPostDetailResource($post->fresh())]);
    }

    public function unpublish(string $id): JsonResponse
    {
        BlogPost::findOrFail($id)->update(['status' => 'draft']);

        return response()->json(['data' => new BlogPostDetailResource(BlogPost::findOrFail($id))]);
    }

    private function syncToc(BlogPost $post, array $items): void
    {
        $post->tocItems()->delete();
        foreach ($items as $index => $item) {
            $post->tocItems()->create([
                'label'       => $item['label'],
                'href'        => $item['href'],
                'order_index' => $index,
            ]);
        }
    }

    private function syncFaq(BlogPost $post, array $items): void
    {
        $post->faqItems()->delete();
        foreach ($items as $index => $item) {
            $post->faqItems()->create([
                'question'    => $item['question'],
                'answer'      => $item['answer'],
                'order_index' => $index,
            ]);
        }
    }

    private function syncRelated(BlogPost $post, array $ids): void
    {
        $post->relatedPosts()->sync($ids);
    }
}
