# Blog Feature — Documentação Laravel API

## Visão Geral

O sistema de blog é composto por:
- 5 tabelas no banco de dados
- 8 endpoints públicos (sem autenticação)
- 8 endpoints admin (autenticados)
- 5 migrations, 5 models, 2 controllers, 5 form requests, 2 resources

---

## 1. Migrations

### 1.1 `blog_categories`

```php
// database/migrations/xxxx_xx_xx_create_blog_categories_table.php

Schema::create('blog_categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('key', 100)->unique();   // "registroHorario", "compliance"
    $table->string('label_pt')->nullable();
    $table->string('label_es')->nullable();
    $table->string('label_en')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### 1.2 `blog_posts`

```php
// database/migrations/xxxx_xx_xx_create_blog_posts_table.php

Schema::create('blog_posts', function (Blueprint $table) {
    $table->uuid('id')->primary();

    // Conteúdo principal
    $table->string('slug')->unique();
    $table->text('title');
    $table->text('excerpt');
    $table->longText('content_html')->nullable();

    // Imagens
    $table->text('cover_url')->nullable();
    $table->text('hero_image_url')->nullable();
    $table->string('hero_image_alt')->nullable();
    $table->string('hero_caption')->nullable();

    // Metadados
    $table->string('author');
    $table->string('category', 100);
    $table->string('audience_tag', 100)->nullable();  // "legal", "hr", "general"
    $table->string('reading_time', 20)->nullable();   // "6 min"
    $table->string('language', 5)->default('pt');     // pt | es | en
    $table->unsignedInteger('trending_score')->default(0);
    $table->boolean('featured')->default(false);

    // Status e publicação
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->timestampTz('published_at')->nullable();

    // SEO
    $table->text('seo_title')->nullable();
    $table->text('seo_description')->nullable();
    $table->text('og_image_url')->nullable();
    $table->text('canonical_url')->nullable();

    $table->timestampsTz();

    // Índices
    $table->index('slug');
    $table->index('status');
    $table->index('category');
    $table->index('language');
    $table->index('featured');
    $table->index(['status', 'published_at']);
    $table->index(['status', 'language', 'category']);
});
```

### 1.3 `blog_toc_items`

```php
// database/migrations/xxxx_xx_xx_create_blog_toc_items_table.php

Schema::create('blog_toc_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
    $table->string('label');
    $table->string('href');    // "#secao-nome"
    $table->unsignedInteger('order_index')->default(0);

    $table->index('post_id');
});
```

### 1.4 `blog_faq_items`

```php
// database/migrations/xxxx_xx_xx_create_blog_faq_items_table.php

Schema::create('blog_faq_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
    $table->text('question');
    $table->text('answer');
    $table->unsignedInteger('order_index')->default(0);

    $table->index('post_id');
});
```

### 1.5 `blog_related_posts`

```php
// database/migrations/xxxx_xx_xx_create_blog_related_posts_table.php

Schema::create('blog_related_posts', function (Blueprint $table) {
    $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
    $table->foreignUuid('related_post_id')->constrained('blog_posts')->cascadeOnDelete();

    $table->primary(['post_id', 'related_post_id']);
});
```

---

## 2. Models

### 2.1 `BlogCategory`

```php
// app/Models/BlogCategory.php

class BlogCategory extends Model
{
    use HasUuids;

    protected $table = 'blog_categories';

    protected $fillable = [
        'key',
        'label_pt',
        'label_es',
        'label_en',
        'sort_order',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category', 'key');
    }
}
```

### 2.2 `BlogPost`

```php
// app/Models/BlogPost.php

class BlogPost extends Model
{
    use HasUuids;

    protected $table = 'blog_posts';

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'content_html',
        'cover_url',
        'hero_image_url',
        'hero_image_alt',
        'hero_caption',
        'author',
        'category',
        'audience_tag',
        'reading_time',
        'language',
        'trending_score',
        'featured',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'og_image_url',
        'canonical_url',
    ];

    protected $casts = [
        'featured'     => 'boolean',
        'published_at' => 'datetime',
        'trending_score' => 'integer',
    ];

    // Scopes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeForLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    // Relationships
    public function tocItems(): HasMany
    {
        return $this->hasMany(BlogTocItem::class, 'post_id')
                    ->orderBy('order_index');
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(BlogFaqItem::class, 'post_id')
                    ->orderBy('order_index');
    }

    public function relatedPosts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'blog_related_posts',
            'post_id',
            'related_post_id'
        );
    }
}
```

### 2.3 `BlogTocItem`

```php
// app/Models/BlogTocItem.php

class BlogTocItem extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'blog_toc_items';

    protected $fillable = ['post_id', 'label', 'href', 'order_index'];
}
```

### 2.4 `BlogFaqItem`

```php
// app/Models/BlogFaqItem.php

class BlogFaqItem extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'blog_faq_items';

    protected $fillable = ['post_id', 'question', 'answer', 'order_index'];
}
```

---

## 3. API Resources (Responses)

### 3.1 `BlogPostListResource` — para listagem (cards)

```php
// app/Http/Resources/BlogPostListResource.php

class BlogPostListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'title'          => $this->title,
            'excerpt'        => $this->excerpt,
            'cover_url'      => $this->cover_url,
            'category'       => $this->category,
            'author'         => $this->author,
            'reading_time'   => $this->reading_time,
            'published_at'   => $this->published_at?->toIso8601String(),
            'featured'       => $this->featured,
            'trending_score' => $this->trending_score,
            'language'       => $this->language,
        ];
    }
}
```

### 3.2 `BlogPostDetailResource` — para artigo completo

```php
// app/Http/Resources/BlogPostDetailResource.php

class BlogPostDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'title'           => $this->title,
            'excerpt'         => $this->excerpt,
            'content_html'    => $this->content_html,
            'cover_url'       => $this->cover_url,
            'hero_image_url'  => $this->hero_image_url,
            'hero_image_alt'  => $this->hero_image_alt,
            'hero_caption'    => $this->hero_caption,
            'author'          => $this->author,
            'category'        => $this->category,
            'audience_tag'    => $this->audience_tag,
            'reading_time'    => $this->reading_time,
            'language'        => $this->language,
            'featured'        => $this->featured,
            'trending_score'  => $this->trending_score,
            'published_at'    => $this->published_at?->toIso8601String(),
            'status'          => $this->status,

            'seo' => [
                'title'        => $this->seo_title,
                'description'  => $this->seo_description,
                'og_image_url' => $this->og_image_url,
                'canonical_url'=> $this->canonical_url,
            ],

            'toc' => $this->tocItems->map(fn($item) => [
                'label' => $item->label,
                'href'  => $item->href,
            ]),

            'faq' => $this->faqItems->map(fn($item) => [
                'question' => $item->question,
                'answer'   => $item->answer,
            ]),

            'related_posts' => $this->relatedPosts->map(fn($post) => [
                'slug'      => $post->slug,
                'title'     => $post->title,
                'excerpt'   => $post->excerpt,
                'cover_url' => $post->cover_url,
                'category'  => $post->category,
                'author'    => $post->author,
                'reading_time' => $post->reading_time,
                'published_at' => $post->published_at?->toIso8601String(),
            ]),
        ];
    }
}
```

---

## 4. Form Requests (Validação)

### 4.1 `StoreBlogPostRequest`

```php
// app/Http/Requests/StoreBlogPostRequest.php

class StoreBlogPostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Obrigatórios
            'title'          => ['required', 'string', 'max:255'],
            'slug'           => ['required', 'string', 'max:255', 'unique:blog_posts,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt'        => ['required', 'string', 'max:500'],
            'author'         => ['required', 'string', 'max:255'],
            'category'       => ['required', 'string', 'max:100'],
            'language'       => ['required', 'in:pt,es,en'],
            'status'         => ['required', 'in:draft,published,archived'],

            // Opcionais
            'content_html'   => ['nullable', 'string'],
            'cover_url'      => ['nullable', 'url', 'max:2048'],
            'hero_image_url' => ['nullable', 'url', 'max:2048'],
            'hero_image_alt' => ['nullable', 'string', 'max:255'],
            'hero_caption'   => ['nullable', 'string', 'max:500'],
            'audience_tag'   => ['nullable', 'string', 'max:100'],
            'reading_time'   => ['nullable', 'string', 'max:20'],
            'trending_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'featured'       => ['nullable', 'boolean'],
            'published_at'   => ['nullable', 'date'],
            'og_image_url'   => ['nullable', 'url', 'max:2048'],
            'canonical_url'  => ['nullable', 'url', 'max:2048'],

            // SEO com aviso de comprimento ideal
            'seo_title'      => ['nullable', 'string', 'max:255'],
            'seo_description'=> ['nullable', 'string', 'max:500'],

            // Relacionamentos aninhados
            'toc'            => ['nullable', 'array'],
            'toc.*.label'    => ['required_with:toc', 'string', 'max:255'],
            'toc.*.href'     => ['required_with:toc', 'string', 'max:255'],

            'faq'            => ['nullable', 'array'],
            'faq.*.question' => ['required_with:faq', 'string'],
            'faq.*.answer'   => ['required_with:faq', 'string'],

            'related_post_ids' => ['nullable', 'array'],
            'related_post_ids.*' => ['uuid', 'exists:blog_posts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Este slug já está em uso por outro post.',
        ];
    }
}
```

### 4.2 `UpdateBlogPostRequest`

```php
// app/Http/Requests/UpdateBlogPostRequest.php

class UpdateBlogPostRequest extends FormRequest
{
    public function rules(): array
    {
        $postId = $this->route('post'); // uuid do post atual

        return [
            'title'          => ['sometimes', 'required', 'string', 'max:255'],
            'slug'           => ['sometimes', 'required', 'string', 'max:255', Rule::unique('blog_posts', 'slug')->ignore($postId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt'        => ['sometimes', 'required', 'string', 'max:500'],
            'author'         => ['sometimes', 'required', 'string', 'max:255'],
            'category'       => ['sometimes', 'required', 'string', 'max:100'],
            'language'       => ['sometimes', 'required', 'in:pt,es,en'],
            'status'         => ['sometimes', 'required', 'in:draft,published,archived'],
            'content_html'   => ['nullable', 'string'],
            'cover_url'      => ['nullable', 'url', 'max:2048'],
            'hero_image_url' => ['nullable', 'url', 'max:2048'],
            'hero_image_alt' => ['nullable', 'string', 'max:255'],
            'hero_caption'   => ['nullable', 'string', 'max:500'],
            'audience_tag'   => ['nullable', 'string', 'max:100'],
            'reading_time'   => ['nullable', 'string', 'max:20'],
            'trending_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'featured'       => ['nullable', 'boolean'],
            'published_at'   => ['nullable', 'date'],
            'seo_title'      => ['nullable', 'string', 'max:255'],
            'seo_description'=> ['nullable', 'string', 'max:500'],
            'og_image_url'   => ['nullable', 'url', 'max:2048'],
            'canonical_url'  => ['nullable', 'url', 'max:2048'],
            'toc'            => ['nullable', 'array'],
            'toc.*.label'    => ['required_with:toc', 'string', 'max:255'],
            'toc.*.href'     => ['required_with:toc', 'string', 'max:255'],
            'faq'            => ['nullable', 'array'],
            'faq.*.question' => ['required_with:faq', 'string'],
            'faq.*.answer'   => ['required_with:faq', 'string'],
            'related_post_ids' => ['nullable', 'array'],
            'related_post_ids.*' => ['uuid', 'exists:blog_posts,id'],
        ];
    }
}
```

---

## 5. Controllers

### 5.1 `PublicBlogController` — Endpoints Públicos

```php
// app/Http/Controllers/Api/V1/Public/PublicBlogController.php

class PublicBlogController extends Controller
{
    // GET /api/v1/public/blog/posts
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'language'  => ['nullable', 'in:pt,es,en'],
            'category'  => ['nullable', 'string'],
            'featured'  => ['nullable', 'boolean'],
            'search'    => ['nullable', 'string', 'max:100'],
            'sort'      => ['nullable', 'in:published_at_desc,trending_score_desc'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = BlogPost::published()
            ->when($request->language,  fn($q) => $q->where('language', $request->language))
            ->when($request->category,  fn($q) => $q->where('category', $request->category))
            ->when($request->featured,  fn($q) => $q->where('featured', true))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%'])
                          ->orWhereRaw('LOWER(excerpt) LIKE ?', ['%' . strtolower($search) . '%']);
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
                'total'    => $posts->total(),
                'page'     => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'last_page'=> $posts->lastPage(),
            ],
        ]);
    }

    // GET /api/v1/public/blog/posts/{slug}
    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::published()
            ->with(['tocItems', 'faqItems', 'relatedPosts'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => new BlogPostDetailResource($post),
        ]);
    }

    // GET /api/v1/public/blog/categories
    public function categories(): JsonResponse
    {
        $categories = BlogCategory::orderBy('sort_order')->get();

        return response()->json([
            'data' => $categories->map(fn($c) => [
                'key'      => $c->key,
                'label_pt' => $c->label_pt,
                'label_es' => $c->label_es,
                'label_en' => $c->label_en,
            ]),
        ]);
    }
}
```

### 5.2 `AdminBlogPostController` — CRUD Admin

```php
// app/Http/Controllers/Api/V1/Admin/AdminBlogPostController.php

class AdminBlogPostController extends Controller
{
    // GET /api/v1/admin/blog/posts
    public function index(Request $request): JsonResponse
    {
        $posts = BlogPost::query()
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->language, fn($q) => $q->where('language', $request->language))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
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

    // GET /api/v1/admin/blog/posts/{id}
    public function show(string $id): JsonResponse
    {
        $post = BlogPost::with(['tocItems', 'faqItems', 'relatedPosts'])
            ->findOrFail($id);

        return response()->json([
            'data' => new BlogPostDetailResource($post),
        ]);
    }

    // POST /api/v1/admin/blog/posts
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

    // PUT /api/v1/admin/blog/posts/{id}
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

    // DELETE /api/v1/admin/blog/posts/{id}
    public function destroy(string $id): JsonResponse
    {
        BlogPost::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    // PATCH /api/v1/admin/blog/posts/{id}/publish
    public function publish(string $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        $post->update([
            'status'       => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);

        return response()->json(['data' => new BlogPostDetailResource($post->fresh())]);
    }

    // PATCH /api/v1/admin/blog/posts/{id}/unpublish
    public function unpublish(string $id): JsonResponse
    {
        BlogPost::findOrFail($id)->update(['status' => 'draft']);

        return response()->json(['data' => new BlogPostDetailResource(BlogPost::findOrFail($id))]);
    }

    // Helpers privados
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
```

---

## 6. Rotas

```php
// routes/api.php

// Públicas — sem autenticação
Route::prefix('v1/public/blog')->name('public.blog.')->group(function () {
    Route::get('posts',           [PublicBlogController::class, 'index'])->name('posts.index');
    Route::get('posts/{slug}',    [PublicBlogController::class, 'show'])->name('posts.show');
    Route::get('categories',      [PublicBlogController::class, 'categories'])->name('categories');
});

// Admin — autenticadas (substitua 'auth:sanctum' pelo middleware que você já usa)
Route::middleware(['auth:sanctum'])->prefix('v1/admin/blog')->name('admin.blog.')->group(function () {
    Route::get   ('posts',                      [AdminBlogPostController::class, 'index'])->name('posts.index');
    Route::post  ('posts',                      [AdminBlogPostController::class, 'store'])->name('posts.store');
    Route::get   ('posts/{id}',                 [AdminBlogPostController::class, 'show'])->name('posts.show');
    Route::put   ('posts/{id}',                 [AdminBlogPostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{id}',                 [AdminBlogPostController::class, 'destroy'])->name('posts.destroy');
    Route::patch ('posts/{id}/publish',         [AdminBlogPostController::class, 'publish'])->name('posts.publish');
    Route::patch ('posts/{id}/unpublish',       [AdminBlogPostController::class, 'unpublish'])->name('posts.unpublish');
});
```

---

## 7. Seeders

### 7.1 `BlogCategorySeeder`

```php
// database/seeders/BlogCategorySeeder.php

class BlogCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'key'       => 'registroHorario',
                'label_pt'  => 'Registro de Horário',
                'label_es'  => 'Registro horario',
                'label_en'  => 'Time tracking',
                'sort_order'=> 1,
            ],
            [
                'key'       => 'compliance',
                'label_pt'  => 'Conformidade / Compliance',
                'label_es'  => 'Cumplimiento / Compliance',
                'label_en'  => 'Compliance',
                'sort_order'=> 2,
            ],
        ];

        foreach ($categories as $category) {
            BlogCategory::updateOrCreate(['key' => $category['key']], $category);
        }
    }
}
```

---

## 8. Exemplo de Payload Completo (POST/PUT)

Enviar no body como `application/json`:

```json
{
  "title": "Registro de Horário Obrigatório em Portugal 2026",
  "slug": "registro-horario-obrigatorio-portugal-2026",
  "excerpt": "Entenda as obrigações legais do registro de ponto em Portugal e como manter sua empresa em conformidade.",
  "content_html": "<h2 id='o-que-e'>O que é o registro de horário?</h2><p>...</p>",
  "author": "Equipa Legal",
  "category": "registroHorario",
  "audience_tag": "legal",
  "language": "pt",
  "status": "published",
  "published_at": "2026-05-14T10:00:00Z",
  "reading_time": "7 min",
  "featured": true,
  "trending_score": 90,

  "cover_url": "https://cdn.example.com/images/blog/registro-horario-portugal.jpg",
  "hero_image_url": "https://cdn.example.com/images/blog/registro-horario-portugal-hero.jpg",
  "hero_image_alt": "Pessoa a registar horário numa empresa portuguesa",
  "hero_caption": "O cumprimento do registo de horário é obrigatório para empresas em Portugal",

  "seo_title": "Registro de Horário Obrigatório em Portugal 2026 | Jornafy",
  "seo_description": "Saiba tudo sobre o registo de horário obrigatório em Portugal. Legislação, multas e como usar software para cumprir a lei.",
  "og_image_url": "https://cdn.example.com/images/blog/og-registro-horario-portugal.jpg",
  "canonical_url": "https://jornafy.com/blog/registro-horario-obrigatorio-portugal-2026",

  "toc": [
    { "label": "O que é o registro de horário?", "href": "#o-que-e" },
    { "label": "Quem é obrigado?",                "href": "#quem-e-obrigado" },
    { "label": "Multas e penalidades",             "href": "#multas" },
    { "label": "Como cumprir a lei",               "href": "#como-cumprir" }
  ],

  "faq": [
    {
      "question": "O registro de horário é obrigatório em Portugal?",
      "answer": "Sim, desde 2019 é obrigatório para todas as empresas com trabalhadores subordinados."
    },
    {
      "question": "Qual a multa por não registrar o horário?",
      "answer": "A coima pode variar entre €1.000 e €10.000 dependendo da dimensão da empresa."
    }
  ],

  "related_post_ids": [
    "uuid-do-post-relacionado-1",
    "uuid-do-post-relacionado-2"
  ]
}
```

---

## 9. Exemplo de Response Completa (GET /posts/:slug)

```json
{
  "data": {
    "id": "01940000-0000-0000-0000-000000000001",
    "slug": "registro-horario-obrigatorio-portugal-2026",
    "title": "Registro de Horário Obrigatório em Portugal 2026",
    "excerpt": "Entenda as obrigações legais...",
    "content_html": "<h2 id='o-que-e'>...</h2>",
    "cover_url": "https://cdn.example.com/...",
    "hero_image_url": "https://cdn.example.com/...",
    "hero_image_alt": "...",
    "hero_caption": "...",
    "author": "Equipa Legal",
    "category": "registroHorario",
    "audience_tag": "legal",
    "reading_time": "7 min",
    "language": "pt",
    "featured": true,
    "trending_score": 90,
    "published_at": "2026-05-14T10:00:00+00:00",
    "status": "published",
    "seo": {
      "title": "Registro de Horário Obrigatório em Portugal 2026 | Jornafy",
      "description": "Saiba tudo sobre o registo de horário...",
      "og_image_url": "https://cdn.example.com/...",
      "canonical_url": "https://jornafy.com/blog/..."
    },
    "toc": [
      { "label": "O que é o registro de horário?", "href": "#o-que-e" },
      { "label": "Quem é obrigado?",                "href": "#quem-e-obrigado" }
    ],
    "faq": [
      {
        "question": "O registro de horário é obrigatório em Portugal?",
        "answer": "Sim, desde 2019..."
      }
    ],
    "related_posts": [
      {
        "slug": "controlo-horario-hostelaria",
        "title": "Controlo de Horário na Hostelaria",
        "excerpt": "...",
        "cover_url": "https://...",
        "category": "registroHorario",
        "author": "Equipa Legal",
        "reading_time": "5 min",
        "published_at": "2026-04-10T00:00:00+00:00"
      }
    ]
  }
}
```

---

## 10. Comandos Artisan para Criar Tudo

Execute na ordem abaixo:

```bash
# Migrations
php artisan make:migration create_blog_categories_table
php artisan make:migration create_blog_posts_table
php artisan make:migration create_blog_toc_items_table
php artisan make:migration create_blog_faq_items_table
php artisan make:migration create_blog_related_posts_table

# Models
php artisan make:model BlogCategory
php artisan make:model BlogPost
php artisan make:model BlogTocItem
php artisan make:model BlogFaqItem

# Resources
php artisan make:resource BlogPostListResource
php artisan make:resource BlogPostDetailResource

# Form Requests
php artisan make:request StoreBlogPostRequest
php artisan make:request UpdateBlogPostRequest

# Controllers
php artisan make:controller Api/V1/Public/PublicBlogController
php artisan make:controller Api/V1/Admin/AdminBlogPostController

# Seeder
php artisan make:seeder BlogCategorySeeder

# Rodar migrations e seeders
php artisan migrate
php artisan db:seed --class=BlogCategorySeeder
```

---

## 11. Checklist de Implementação

### Banco de Dados
- [ ] Criar e rodar as 5 migrations
- [ ] Rodar o `BlogCategorySeeder` com as categorias iniciais
- [ ] Verificar índices criados corretamente

### Models
- [ ] `BlogCategory` com `HasUuids` e relacionamento `posts()`
- [ ] `BlogPost` com `HasUuids`, scopes e relacionamentos `tocItems`, `faqItems`, `relatedPosts`
- [ ] `BlogTocItem` com `$timestamps = false`
- [ ] `BlogFaqItem` com `$timestamps = false`

### Resources
- [ ] `BlogPostListResource` (campos resumidos para cards)
- [ ] `BlogPostDetailResource` (campos completos + nested seo, toc, faq, related_posts)

### Form Requests
- [ ] `StoreBlogPostRequest` com todas as regras + validação de slug único
- [ ] `UpdateBlogPostRequest` com `Rule::unique()->ignore()` no slug
- [ ] Ambas validam arrays `toc`, `faq` e `related_post_ids`

### Controllers
- [ ] `PublicBlogController`: `index`, `show`, `categories`
- [ ] `AdminBlogPostController`: `index`, `show`, `store`, `update`, `destroy`, `publish`, `unpublish`
- [ ] `store` e `update` usam `DB::transaction()` para salvar toc/faq/related atomicamente

### Rotas
- [ ] Rotas públicas sem middleware
- [ ] Rotas admin com middleware de autenticação
- [ ] Nomes de rota configurados (útil para geração de links e testes)

### Testes (recomendado)
- [ ] `GET /posts` retorna apenas `status=published`
- [ ] `GET /posts` filtra por `language`, `category`, `featured`, `search`
- [ ] `GET /posts/:slug` retorna 404 para slug inexistente ou draft
- [ ] `POST /admin/posts` salva toc/faq/related em transação
- [ ] `PUT /admin/posts/:id` não permite slug duplicado de outro post
- [ ] `PATCH /admin/posts/:id/publish` seta `published_at` se não informado
