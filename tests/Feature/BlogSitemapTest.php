<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSitemapTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

    /**
     * Build an absolute URL for a non-API (web) route.
     *
     * `/sitemap-blog.xml` is registered in routes/web.php (no `/api` prefix),
     * while APP_URL already includes `/api`, so the `/api` segment must be
     * stripped before appending the path.
     */
    private function siteUrl(string $path): string
    {
        $root = preg_replace('#/api/?$#', '', config('app.url'));

        return rtrim($root, '/').'/'.ltrim($path, '/');
    }

    private function createPost(array $overrides = []): BlogPost
    {
        self::$counter++;
        $n = self::$counter;

        return BlogPost::create(array_merge([
            'slug' => "post-{$n}",
            'title' => ['pt' => "Título {$n}", 'es' => "Título {$n}", 'en' => "Title {$n}"],
            'excerpt' => ['pt' => "Resumo {$n}", 'es' => "Resumen {$n}", 'en' => "Excerpt {$n}"],
            'author' => 'Carlos',
            'category' => 'tutoriais',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'canonical_url' => "https://jornafy.com/blog/post-{$n}",
        ], $overrides));
    }

    public function test_sitemap_returns_200_with_xml_content_type(): void
    {
        $response = $this->get($this->siteUrl('/sitemap-blog.xml'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_includes_blog_listing_url(): void
    {
        $response = $this->get($this->siteUrl('/sitemap-blog.xml'));

        $response->assertOk();
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $response->assertSee("<loc>{$frontendUrl}/blog</loc>", false);
    }

    public function test_sitemap_includes_only_published_posts(): void
    {
        $published = $this->createPost();
        $draft = $this->createPost(['status' => 'draft', 'published_at' => null]);
        $future = $this->createPost(['published_at' => now()->addDay()]);

        $response = $this->get($this->siteUrl('/sitemap-blog.xml'));

        $response->assertOk();
        $response->assertSee("<loc>{$published->canonical_url}</loc>", false);
        $response->assertDontSee($draft->slug, false);
        $response->assertDontSee($future->slug, false);
    }

    public function test_sitemap_uses_canonical_url_when_present_else_default_blog_url(): void
    {
        $withCanonical = $this->createPost(['canonical_url' => 'https://jornafy.com/blog/custom-canonical']);
        $withoutCanonical = $this->createPost(['canonical_url' => null]);

        $response = $this->get($this->siteUrl('/sitemap-blog.xml'));

        $response->assertOk();
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $response->assertSee('<loc>https://jornafy.com/blog/custom-canonical</loc>', false);
        $response->assertSee("<loc>{$frontendUrl}/blog/{$withoutCanonical->slug}</loc>", false);
    }

    public function test_sitemap_lastmod_is_iso8601_from_updated_at(): void
    {
        $post = $this->createPost();

        $response = $this->get($this->siteUrl('/sitemap-blog.xml'));

        $response->assertOk();
        $response->assertSee("<lastmod>{$post->updated_at->toAtomString()}</lastmod>", false);
    }
}
