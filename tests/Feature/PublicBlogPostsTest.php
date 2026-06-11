<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

    /**
     * Build an absolute URL for the public blog API.
     *
     * Using an absolute URL avoids relying on the test client's dynamic
     * root-URL resolution, which can drift between requests within the
     * same test once APP_URL already includes the `/api` segment.
     */
    private function apiUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private function createPost(array $overrides = []): BlogPost
    {
        self::$counter++;
        $n = self::$counter;

        return BlogPost::create(array_merge([
            'slug' => "post-{$n}",
            'title' => [
                'pt' => "Título PT {$n}",
                'es' => "Título ES {$n}",
                'en' => "Title EN {$n}",
            ],
            'excerpt' => [
                'pt' => "Resumo PT {$n}",
                'es' => "Resumen ES {$n}",
                'en' => "Excerpt EN {$n}",
            ],
            'content_html' => [
                'pt' => "<p>Conteúdo PT {$n}</p>",
                'es' => "<p>Contenido ES {$n}</p>",
                'en' => "<p>Content EN {$n}</p>",
            ],
            'toc' => [
                'pt' => [['label' => 'Introdução', 'href' => '#intro']],
                'es' => [['label' => 'Introducción', 'href' => '#intro']],
                'en' => [['label' => 'Introduction', 'href' => '#intro']],
            ],
            'cover_url' => "https://cdn.exemplo.com/blog/covers/post-{$n}.jpg",
            'hero_image_url' => "https://cdn.exemplo.com/blog/heroes/post-{$n}.jpg",
            'hero_image_alt' => [
                'pt' => "Alt PT {$n}",
                'es' => "Alt ES {$n}",
                'en' => "Alt EN {$n}",
            ],
            'hero_caption' => [
                'pt' => "Legenda PT {$n}",
                'es' => "Leyenda ES {$n}",
                'en' => "Caption EN {$n}",
            ],
            'author' => 'Carlos',
            'category' => 'tutoriais',
            'audience_tag' => 'rh',
            'reading_time' => '5 min',
            'trending_score' => 0,
            'featured' => false,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'seo_title' => [
                'pt' => "SEO PT {$n}",
                'es' => "SEO ES {$n}",
                'en' => "SEO EN {$n}",
            ],
            'seo_description' => [
                'pt' => "SEO Desc PT {$n}",
                'es' => "SEO Desc ES {$n}",
                'en' => "SEO Desc EN {$n}",
            ],
            'og_image_url' => "https://cdn.exemplo.com/blog/og/post-{$n}.jpg",
            'canonical_url' => "https://jornafy.com/blog/post-{$n}",
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // index()
    // -----------------------------------------------------------------

    public function test_index_default_per_page_is_9_and_meta_has_total_page_per_page_only(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->createPost();
        }

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts'));

        $response->assertOk();
        $this->assertCount(9, $response->json('data'));

        $meta = $response->json('meta');
        $this->assertSame(['total', 'page', 'per_page'], array_keys($meta));
        $this->assertSame(12, $meta['total']);
        $this->assertSame(1, $meta['page']);
        $this->assertSame(9, $meta['per_page']);
    }

    public function test_index_defaults_to_lang_es(): void
    {
        $post = $this->createPost();

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts'));

        $response->assertOk();
        $this->assertSame($post->getTranslation('title', 'es'), $response->json('data.0.title'));
    }

    public function test_index_invalid_lang_falls_back_to_es_without_422(): void
    {
        $post = $this->createPost();

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts?lang=fr'));

        $response->assertOk();
        $this->assertSame($post->getTranslation('title', 'es'), $response->json('data.0.title'));
    }

    public function test_index_filters_by_category(): void
    {
        $this->createPost(['category' => 'tutoriais']);
        $this->createPost(['category' => 'compliance']);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts?category=compliance'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('compliance', $response->json('data.0.category'));
    }

    public function test_index_q_matches_title_in_requested_lang(): void
    {
        $this->createPost([
            'title' => [
                'pt' => 'Tutorial Diferente PT',
                'es' => 'Tutorial Exclusivo Especiales ES',
                'en' => 'Different Tutorial EN',
            ],
        ]);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts?lang=es&q=Especiales'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_q_does_not_match_other_languages(): void
    {
        $this->createPost([
            'title' => [
                'pt' => 'Tutorial Diferente PT',
                'es' => 'Tutorial Exclusivo Especiales ES',
                'en' => 'Different Tutorial EN',
            ],
        ]);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts?lang=pt&q=Especiales'));

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_q_matches_excerpt(): void
    {
        $this->createPost([
            'excerpt' => [
                'pt' => 'Resumo comum',
                'es' => 'Resumen común',
                'en' => 'Excerpt with UniqueExcerptTerm inside',
            ],
        ]);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts?lang=en&q=UniqueExcerptTerm'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_item_includes_audience_tag_and_excludes_language(): void
    {
        $this->createPost(['audience_tag' => 'gestores']);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts'));

        $response->assertOk();
        $this->assertSame('gestores', $response->json('data.0.audience_tag'));
        $this->assertArrayNotHasKey('language', $response->json('data.0'));
    }

    public function test_index_excludes_post_with_future_published_at(): void
    {
        $this->createPost(['published_at' => now()->addDay()]);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts'));

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_index_excludes_draft_and_archived_posts(): void
    {
        $this->createPost(['status' => 'draft', 'published_at' => null]);
        $this->createPost(['status' => 'archived']);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts'));

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    // -----------------------------------------------------------------
    // show()
    // -----------------------------------------------------------------

    public function test_show_returns_flattened_shape_with_full_related_posts(): void
    {
        $related = $this->createPost(['audience_tag' => 'legal']);
        $post = $this->createPost();

        $post->relatedPosts()->sync([$related->id]);
        $post->faqItems()->create([
            'question' => [
                'pt' => 'Pergunta PT?',
                'es' => '¿Pregunta ES?',
                'en' => 'Question EN?',
            ],
            'answer' => [
                'pt' => 'Resposta PT.',
                'es' => 'Respuesta ES.',
                'en' => 'Answer EN.',
            ],
            'order_index' => 0,
        ]);

        $response = $this->getJson($this->apiUrl("/v1/public/blog/posts/{$post->slug}?lang=en"));

        $response->assertOk();
        $response->assertJsonMissingPath('data.status');
        $response->assertJsonMissingPath('data.seo');

        $this->assertSame($post->getTranslation('seo_title', 'en'), $response->json('data.seo_title'));
        $this->assertSame($post->getTranslation('seo_description', 'en'), $response->json('data.seo_description'));
        $this->assertSame($post->og_image_url, $response->json('data.og_image_url'));
        $this->assertSame($post->canonical_url, $response->json('data.canonical_url'));

        $this->assertSame('Question EN?', $response->json('data.faq.0.question'));
        $this->assertSame('Answer EN.', $response->json('data.faq.0.answer'));

        $response->assertJsonStructure([
            'data' => [
                'related_posts' => [
                    '*' => ['id', 'slug', 'title', 'excerpt', 'category', 'audience_tag', 'author', 'published_at', 'reading_time', 'cover_url'],
                ],
            ],
        ]);

        $this->assertSame($related->id, $response->json('data.related_posts.0.id'));
        $this->assertSame('legal', $response->json('data.related_posts.0.audience_tag'));
    }

    public function test_show_returns_404_with_exact_message_for_nonexistent_slug(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson($this->apiUrl('/v1/public/blog/posts/nao-existe'));

        $response->assertStatus(404);
        $this->assertSame(['message' => 'Post not found'], $response->json());
    }

    public function test_show_returns_404_for_draft_post(): void
    {
        config(['app.debug' => false]);

        $post = $this->createPost(['status' => 'draft', 'published_at' => null]);

        $response = $this->getJson($this->apiUrl("/v1/public/blog/posts/{$post->slug}"));

        $response->assertStatus(404);
        $this->assertSame(['message' => 'Post not found'], $response->json());
    }

    public function test_show_returns_404_for_post_with_future_published_at(): void
    {
        config(['app.debug' => false]);

        $post = $this->createPost(['published_at' => now()->addDay()]);

        $response = $this->getJson($this->apiUrl("/v1/public/blog/posts/{$post->slug}"));

        $response->assertStatus(404);
        $this->assertSame(['message' => 'Post not found'], $response->json());
    }

    public function test_show_defaults_to_lang_es(): void
    {
        $post = $this->createPost();

        $response = $this->getJson($this->apiUrl("/v1/public/blog/posts/{$post->slug}"));

        $response->assertOk();
        $this->assertSame($post->getTranslation('title', 'es'), $response->json('data.title'));
    }

    public function test_show_invalid_lang_falls_back_to_es(): void
    {
        $post = $this->createPost();

        $response = $this->getJson($this->apiUrl("/v1/public/blog/posts/{$post->slug}?lang=fr"));

        $response->assertOk();
        $this->assertSame($post->getTranslation('title', 'es'), $response->json('data.title'));
    }
}
