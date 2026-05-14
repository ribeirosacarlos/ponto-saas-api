<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('slug')->unique();
            $table->text('title');
            $table->text('excerpt');
            $table->longText('content_html')->nullable();

            $table->text('cover_url')->nullable();
            $table->text('hero_image_url')->nullable();
            $table->string('hero_image_alt')->nullable();
            $table->string('hero_caption')->nullable();

            $table->string('author');
            $table->string('category', 100);
            $table->string('audience_tag', 100)->nullable();
            $table->string('reading_time', 20)->nullable();
            $table->string('language', 5)->default('pt');
            $table->unsignedInteger('trending_score')->default(0);
            $table->boolean('featured')->default(false);

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestampTz('published_at')->nullable();

            $table->text('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('og_image_url')->nullable();
            $table->text('canonical_url')->nullable();

            $table->timestampsTz();

            $table->index('slug');
            $table->index('status');
            $table->index('category');
            $table->index('language');
            $table->index('featured');
            $table->index(['status', 'published_at']);
            $table->index(['status', 'language', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
