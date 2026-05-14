# Blog Feature — Ajustes: i18n + Upload S3

> Aplique sobre a implementação gerada pela doc `blog-feature-laravel.md`.
> Não é um rebuild — são apenas as alterações necessárias.

---

## 1. Instalar o pacote

```bash
composer require spatie/laravel-translatable
```

---

## 2. Migration de Ajuste

Crie uma nova migration (não altere as anteriores):

```bash
php artisan make:migration adjust_blog_posts_for_translatable
```

```php
public function up(): void
{
    Schema::table('blog_posts', function (Blueprint $table) {
        // Remover coluna language (não precisa mais — um post tem os 3 idiomas)
        $table->dropIndex(['language']);
        $table->dropColumn('language');

        // Converter campos de texto para JSON
        $table->json('title')->change();
        $table->json('excerpt')->change();
        $table->json('content_html')->nullable()->change();
        $table->json('seo_title')->nullable()->change();
        $table->json('seo_description')->nullable()->change();
        $table->json('hero_image_alt')->nullable()->change();
        $table->json('hero_caption')->nullable()->change();

        // Novo campo: toc traduzível (sai da tabela blog_toc_items)
        $table->json('toc')->nullable()->after('content_html');
    });

    // TOC agora vive em blog_posts.toc — tabela separada não é mais necessária
    Schema::dropIfExists('blog_toc_items');
}

public function down(): void
{
    Schema::table('blog_posts', function (Blueprint $table) {
        $table->string('language', 5)->default('pt');
        $table->index('language');
        $table->text('title')->change();
        $table->text('excerpt')->change();
        $table->longText('content_html')->nullable()->change();
        $table->text('seo_title')->nullable()->change();
        $table->text('seo_description')->nullable()->change();
        $table->string('hero_image_alt')->nullable()->change();
        $table->string('hero_caption')->nullable()->change();
        $table->dropColumn('toc');
    });

    Schema::create('blog_toc_items', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
        $table->string('label');
        $table->string('href');
        $table->unsignedInteger('order_index')->default(0);
    });
}
```

> **Dependência:** instale `doctrine/dbal` se o Laravel reclamar no `->change()`:
> ```bash
> composer require doctrine/dbal
> ```

Também ajuste `blog_faq_items` para suportar tradução:

```bash
php artisan make:migration adjust_blog_faq_items_for_translatable
```

```php
public function up(): void
{
    Schema::table('blog_faq_items', function (Blueprint $table) {
        $table->json('question')->change();
        $table->json('answer')->change();
    });
}
```

---

## 3. Model `BlogPost` — Atualizar

Substitua o model anterior por este:

```php
// app/Models/BlogPost.php

use Spatie\Translatable\HasTranslations;

class BlogPost extends Model
{
    use HasUuids, HasTranslations;

    protected $table = 'blog_posts';

    // Campos traduzíveis (JSON no banco)
    public array $translatable = [
        'title',
        'excerpt',
        'content_html',
        'hero_image_alt',
        'hero_caption',
        'seo_title',
        'seo_description',
        'toc',           // array de {label, href} por idioma
    ];

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'content_html',
        'toc',
        'cover_url',
        'hero_image_url',
        'hero_image_alt',
        'hero_caption',
        'author',
        'category',
        'audience_tag',
        'reading_time',
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
        'featured'       => 'boolean',
        'published_at'   => 'datetime',
        'trending_score' => 'integer',
        'toc'            => 'array',  // spatie cuida do JSON; cast garante array no retorno
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(BlogFaqItem::class, 'post_id')->orderBy('order_index');
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

---

## 4. Model `BlogFaqItem` — Atualizar

```php
// app/Models/BlogFaqItem.php

use Spatie\Translatable\HasTranslations;

class BlogFaqItem extends Model
{
    use HasUuids, HasTranslations;

    public $timestamps = false;
    protected $table = 'blog_faq_items';

    public array $translatable = ['question', 'answer'];

    protected $fillable = ['post_id', 'question', 'answer', 'order_index'];
}
```

---

## 5. Form Requests — Atualizar validação

Os campos traduzíveis agora recebem um objeto `{"pt": "...", "es": "...", "en": "..."}`.

### `StoreBlogPostRequest`

Substitua apenas as regras dos campos traduzíveis:

```php
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
        // Campos não traduzíveis — iguais à doc anterior
        'slug'             => ['required', 'string', 'max:255', 'unique:blog_posts,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        'author'           => ['required', 'string', 'max:255'],
        'category'         => ['required', 'string', 'max:100'],
        'status'           => ['required', 'in:draft,published,archived'],
        'cover_url'        => ['nullable', 'url', 'max:2048'],
        'hero_image_url'   => ['nullable', 'url', 'max:2048'],
        'audience_tag'     => ['nullable', 'string', 'max:100'],
        'reading_time'     => ['nullable', 'string', 'max:20'],
        'trending_score'   => ['nullable', 'integer', 'min:0', 'max:100'],
        'featured'         => ['nullable', 'boolean'],
        'published_at'     => ['nullable', 'date'],
        'og_image_url'     => ['nullable', 'url', 'max:2048'],
        'canonical_url'    => ['nullable', 'url', 'max:2048'],
        'faq'              => ['nullable', 'array'],
        'faq.*.question'   => ['required_with:faq', 'array'],  // agora é objeto {pt,es,en}
        'faq.*.answer'     => ['required_with:faq', 'array'],
        'faq.*.question.pt'=> ['required_with:faq', 'string'],
        'faq.*.question.es'=> ['required_with:faq', 'string'],
        'faq.*.question.en'=> ['required_with:faq', 'string'],
        'faq.*.answer.pt'  => ['required_with:faq', 'string'],
        'faq.*.answer.es'  => ['required_with:faq', 'string'],
        'faq.*.answer.en'  => ['required_with:faq', 'string'],
        'related_post_ids'   => ['nullable', 'array'],
        'related_post_ids.*' => ['uuid', 'exists:blog_posts,id'],
    ]);
}
```

### `UpdateBlogPostRequest`

Mesma estrutura do Store, mas troque:
- `'required'` → `'sometimes', 'required'` nos campos obrigatórios
- `'unique:blog_posts,slug'` → `Rule::unique('blog_posts', 'slug')->ignore($this->route('id'))`

---

## 6. Controllers — Ajuste do Locale

### `PublicBlogController`

Adicione `app()->setLocale()` nos métodos `index` e `show`. O Resource funciona automaticamente.

```php
public function index(Request $request): JsonResponse
{
    $language = $request->input('language', 'pt');
    app()->setLocale($language);

    // remover o filtro ->where('language', ...) que existia antes
    $query = BlogPost::published()
        ->when($request->category,  fn($q) => $q->where('category', $request->category))
        ->when($request->featured,  fn($q) => $q->where('featured', true))
        ->when($request->search, function ($q, $search) {
            // busca no JSON: funciona no MySQL 5.7+ e PostgreSQL
            $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.pt')) LIKE ?", ["%{$search}%"])
              ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.es')) LIKE ?", ["%{$search}%"])
              ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) LIKE ?", ["%{$search}%"]);
        });

    // ... resto igual
}

public function show(Request $request, string $slug): JsonResponse
{
    $language = $request->input('language', 'pt');
    app()->setLocale($language);

    $post = BlogPost::published()
        ->with(['faqItems', 'relatedPosts'])  // tocItems removido — toc está no post
        ->where('slug', $slug)
        ->firstOrFail();

    return response()->json(['data' => new BlogPostDetailResource($post)]);
}
```

### `AdminBlogPostController`

No admin, retorne **todos os idiomas** (para o painel de edição preencher os campos):

```php
public function show(string $id): JsonResponse
{
    // não seta locale — retorna o objeto completo com todos os idiomas
    $post = BlogPost::with(['faqItems', 'relatedPosts'])->findOrFail($id);

    return response()->json(['data' => new BlogPostAdminResource($post)]);
}
```

Crie um `BlogPostAdminResource` que retorna `getTranslations()` em vez do valor traduzido:

```php
// app/Http/Resources/BlogPostAdminResource.php

class BlogPostAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'slug'   => $this->slug,
            'status' => $this->status,

            // Retorna objeto completo {"pt": "...", "es": "...", "en": "..."}
            'title'           => $this->getTranslations('title'),
            'excerpt'         => $this->getTranslations('excerpt'),
            'content_html'    => $this->getTranslations('content_html'),
            'hero_image_alt'  => $this->getTranslations('hero_image_alt'),
            'hero_caption'    => $this->getTranslations('hero_caption'),
            'seo_title'       => $this->getTranslations('seo_title'),
            'seo_description' => $this->getTranslations('seo_description'),
            'toc'             => $this->getTranslations('toc'),

            // Campos simples
            'author'          => $this->author,
            'category'        => $this->category,
            'audience_tag'    => $this->audience_tag,
            'cover_url'       => $this->cover_url,
            'hero_image_url'  => $this->hero_image_url,
            'og_image_url'    => $this->og_image_url,
            'canonical_url'   => $this->canonical_url,
            'reading_time'    => $this->reading_time,
            'trending_score'  => $this->trending_score,
            'featured'        => $this->featured,
            'published_at'    => $this->published_at?->toIso8601String(),

            'faq' => $this->faqItems->map(fn($item) => [
                'id'       => $item->id,
                'question' => $item->getTranslations('question'),
                'answer'   => $item->getTranslations('answer'),
            ]),

            'related_posts' => $this->relatedPosts->map(fn($p) => [
                'id'    => $p->id,
                'slug'  => $p->slug,
                'title' => $p->getTranslation('title', 'pt'), // só pt no admin
            ]),
        ];
    }
}
```

---

## 7. Payload Atualizado (POST /admin/blog/posts)

```json
{
  "slug": "registro-horario-obrigatorio-portugal-2026",
  "author": "Equipa Legal",
  "category": "registroHorario",
  "status": "published",
  "published_at": "2026-05-14T10:00:00Z",
  "reading_time": "7 min",
  "featured": true,
  "trending_score": 90,
  "cover_url": "https://seu-bucket.s3.amazonaws.com/blog/covers/registro-pt.jpg",
  "hero_image_url": "https://seu-bucket.s3.amazonaws.com/blog/heroes/registro-pt.jpg",
  "og_image_url": "https://seu-bucket.s3.amazonaws.com/blog/og/registro-pt.jpg",
  "canonical_url": "https://jornafy.com/blog/registro-horario-obrigatorio-portugal-2026",

  "title": {
    "pt": "Registro de Horário Obrigatório em Portugal 2026",
    "es": "Registro Horario Obligatorio en Portugal 2026",
    "en": "Mandatory Time Tracking in Portugal 2026"
  },
  "excerpt": {
    "pt": "Entenda as obrigações legais do registro de ponto em Portugal.",
    "es": "Entienda las obligaciones legales del registro horario en Portugal.",
    "en": "Understand the legal obligations for time tracking in Portugal."
  },
  "content_html": {
    "pt": "<h2 id='o-que-e'>O que é?</h2><p>...</p>",
    "es": "<h2 id='que-es'>¿Qué es?</h2><p>...</p>",
    "en": "<h2 id='what-is'>What is it?</h2><p>...</p>"
  },
  "seo_title": {
    "pt": "Registro de Horário Obrigatório Portugal 2026 | Jornafy",
    "es": "Registro Horario Obligatorio Portugal 2026 | Jornafy",
    "en": "Mandatory Time Tracking Portugal 2026 | Jornafy"
  },
  "seo_description": {
    "pt": "Saiba tudo sobre o registo de horário obrigatório em Portugal.",
    "es": "Sepa todo sobre el registro horario obligatorio en Portugal.",
    "en": "Learn everything about mandatory time tracking in Portugal."
  },
  "toc": {
    "pt": [
      { "label": "O que é?",          "href": "#o-que-e" },
      { "label": "Quem é obrigado?",  "href": "#quem-e-obrigado" }
    ],
    "es": [
      { "label": "¿Qué es?",          "href": "#que-es" },
      { "label": "¿Quién está obligado?", "href": "#quien-obligado" }
    ],
    "en": [
      { "label": "What is it?",       "href": "#what-is" },
      { "label": "Who is required?",  "href": "#who-required" }
    ]
  },
  "faq": [
    {
      "question": {
        "pt": "O registro é obrigatório?",
        "es": "¿El registro es obligatorio?",
        "en": "Is registration mandatory?"
      },
      "answer": {
        "pt": "Sim, desde 2019.",
        "es": "Sí, desde 2019.",
        "en": "Yes, since 2019."
      }
    }
  ],
  "related_post_ids": ["uuid-1", "uuid-2"]
}
```

---

## 8. Upload de Imagens via S3 (Presigned URL)

### Por que Presigned URL

O cliente (browser/admin) envia o arquivo **diretamente ao S3** — o seu servidor Laravel **nunca recebe o binário**. Apenas gera um token assinado. Ideal para 1 post/dia.

```
Browser → POST /api/v1/admin/blog/uploads/presign  (só metadados, ~100 bytes)
Laravel → Gera URL assinada no S3
Browser → PUT direto no S3 com a URL assinada  (Laravel não vê o arquivo)
Browser → Salva a URL pública no blog_post
```

### Configuração

```bash
composer require league/flysystem-aws-s3-v3
```

`.env`:
```
AWS_ACCESS_KEY_ID=sua_key
AWS_SECRET_ACCESS_KEY=seu_secret
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=seu-bucket-blog
AWS_URL=https://seu-bucket.s3.eu-west-1.amazonaws.com
```

`config/filesystems.php` — o disco `s3` já vem configurado por padrão no Laravel.

### Endpoint de Presigned URL

```bash
php artisan make:controller Api/V1/Admin/BlogUploadController
```

```php
// app/Http/Controllers/Api/V1/Admin/BlogUploadController.php

use Aws\S3\S3Client;

class BlogUploadController extends Controller
{
    // POST /api/v1/admin/blog/uploads/presign
    public function presign(Request $request): JsonResponse
    {
        $request->validate([
            'filename'  => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'in:image/jpeg,image/png,image/webp'],
            'folder'    => ['required', 'in:covers,heroes,og'],  // pastas permitidas
        ]);

        $extension = match ($request->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        };

        // Gera nome único para evitar colisões
        $key = sprintf(
            'blog/%s/%s.%s',
            $request->folder,               // covers | heroes | og
            str(str()->ulid())->lower(),     // ulid único
            $extension
        );

        $client = new S3Client([
            'region'      => config('filesystems.disks.s3.region'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        // URL assinada válida por 5 minutos — suficiente para o upload
        $command = $client->getCommand('PutObject', [
            'Bucket'       => config('filesystems.disks.s3.bucket'),
            'Key'          => $key,
            'ContentType'  => $request->mime_type,
            'CacheControl' => 'public, max-age=31536000',  // 1 ano de cache
        ]);

        $presignedUrl = (string) $client->createPresignedRequest($command, '+5 minutes')->getUri();

        $publicUrl = config('filesystems.disks.s3.url') . '/' . $key;

        return response()->json([
            'upload_url' => $presignedUrl,  // browser faz PUT aqui
            'public_url' => $publicUrl,     // salvar no blog_post após upload
        ]);
    }
}
```

**Adicionar rota:**

```php
Route::middleware(['auth:sanctum'])->prefix('v1/admin/blog')->group(function () {
    // ... rotas existentes ...
    Route::post('uploads/presign', [BlogUploadController::class, 'presign']);
});
```

### Como o admin usa (fluxo no frontend)

```javascript
// 1. Pedir URL assinada
const { upload_url, public_url } = await api.post('/admin/blog/uploads/presign', {
  filename:  'minha-foto.jpg',
  mime_type: 'image/jpeg',
  folder:    'covers',        // covers | heroes | og
})

// 2. Enviar direto ao S3 (sem passar pelo servidor)
await fetch(upload_url, {
  method:  'PUT',
  body:    file,              // File object do <input type="file">
  headers: { 'Content-Type': 'image/jpeg' },
})

// 3. Salvar a URL pública no formulário do post
form.cover_url = public_url
```

### Configuração do bucket S3

No console AWS, configure o bucket com:

**Bucket Policy** (acesso público de leitura):
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicRead",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::seu-bucket-blog/*"
    }
  ]
}
```

**CORS** (permitir upload do browser):
```json
[
  {
    "AllowedHeaders": ["Content-Type"],
    "AllowedMethods": ["PUT"],
    "AllowedOrigins": ["https://seu-admin.com"],
    "MaxAgeSeconds": 300
  }
]
```

**IAM — permissões mínimas para o Laravel** (apenas o necessário):
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::seu-bucket-blog/blog/*"
    }
  ]
}
```

---

## 9. Rotas Finais Completas

```php
// Públicas
Route::prefix('v1/public/blog')->group(function () {
    Route::get('posts',        [PublicBlogController::class, 'index']);
    Route::get('posts/{slug}', [PublicBlogController::class, 'show']);
    Route::get('categories',   [PublicBlogController::class, 'categories']);
});

// Admin
Route::middleware(['auth:sanctum'])->prefix('v1/admin/blog')->group(function () {
    Route::get   ('posts',                [AdminBlogPostController::class, 'index']);
    Route::post  ('posts',                [AdminBlogPostController::class, 'store']);
    Route::get   ('posts/{id}',           [AdminBlogPostController::class, 'show']);
    Route::put   ('posts/{id}',           [AdminBlogPostController::class, 'update']);
    Route::delete('posts/{id}',           [AdminBlogPostController::class, 'destroy']);
    Route::patch ('posts/{id}/publish',   [AdminBlogPostController::class, 'publish']);
    Route::patch ('posts/{id}/unpublish', [AdminBlogPostController::class, 'unpublish']);
    Route::post  ('uploads/presign',      [BlogUploadController::class,    'presign']);
});
```

---

## 10. Checklist de Ajustes

### Banco
- [ ] Criar e rodar migration `adjust_blog_posts_for_translatable`
- [ ] Criar e rodar migration `adjust_blog_faq_items_for_translatable`

### Pacote
- [ ] `composer require spatie/laravel-translatable`
- [ ] `composer require league/flysystem-aws-s3-v3`

### Models
- [ ] `BlogPost` — adicionar `HasTranslations`, atualizar `$translatable`, remover `language` do `$fillable`, adicionar `toc` no `$fillable` e `$casts`
- [ ] `BlogFaqItem` — adicionar `HasTranslations`, atualizar `$translatable`
- [ ] Deletar `BlogTocItem` (model não é mais necessário)

### Resources
- [ ] `BlogPostDetailResource` — remover `tocItems` da relação, usar `$this->toc` direto
- [ ] Criar `BlogPostAdminResource` — retorna `getTranslations()` para o painel

### Form Requests
- [ ] `StoreBlogPostRequest` — validação de campos traduzíveis como `title.pt`, `title.es`, `title.en`
- [ ] `UpdateBlogPostRequest` — mesma estrutura com `sometimes`

### Controllers
- [ ] `PublicBlogController` — adicionar `app()->setLocale()`, remover `->with('tocItems')`
- [ ] `AdminBlogPostController` — usar `BlogPostAdminResource` no `show`, remover `syncToc()` do `store`/`update`

### S3
- [ ] Criar bucket no AWS S3
- [ ] Configurar Bucket Policy (leitura pública)
- [ ] Configurar CORS (PUT do browser)
- [ ] Criar IAM user com permissão mínima (`s3:PutObject`, `s3:DeleteObject`)
- [ ] Adicionar credenciais no `.env`
- [ ] Criar `BlogUploadController` com endpoint `POST /uploads/presign`
