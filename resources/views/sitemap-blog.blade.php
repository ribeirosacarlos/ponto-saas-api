<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{{ $frontendUrl }}/blog</loc>
    <lastmod>{{ $now->toAtomString() }}</lastmod>
  </url>
@foreach ($posts as $post)
  <url>
    <loc>{{ $post->canonical_url ?: $frontendUrl . '/blog/' . $post->slug }}</loc>
    <lastmod>{{ ($post->updated_at ?? $post->published_at)->toAtomString() }}</lastmod>
  </url>
@endforeach
</urlset>
