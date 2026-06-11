<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Response;

class BlogSitemapController extends Controller
{
    public function index(): Response
    {
        $posts = BlogPost::published()->orderByDesc('published_at')->get();
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $xml = view('sitemap-blog', [
            'posts' => $posts,
            'frontendUrl' => $frontendUrl,
            'now' => now(),
        ])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
