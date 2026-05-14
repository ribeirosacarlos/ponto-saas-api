<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex(['language']);
            $table->dropColumn('language');
        });

        $textToJson = ['title', 'excerpt', 'content_html', 'seo_title', 'seo_description', 'hero_image_alt', 'hero_caption'];
        foreach ($textToJson as $col) {
            DB::statement("ALTER TABLE blog_posts ALTER COLUMN \"{$col}\" TYPE json USING \"{$col}\"::json");
        }

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->json('toc')->nullable()->after('content_html');
        });

        Schema::dropIfExists('blog_toc_items');
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('toc');
            $table->string('language', 5)->default('pt');
        });

        $jsonToText = ['title', 'excerpt', 'content_html', 'seo_title', 'seo_description', 'hero_image_alt', 'hero_caption'];
        foreach ($jsonToText as $col) {
            DB::statement("ALTER TABLE blog_posts ALTER COLUMN \"{$col}\" TYPE text USING \"{$col}\"::text");
        }

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->index('language');
        });

        Schema::create('blog_toc_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('label');
            $table->string('href');
            $table->unsignedInteger('order_index')->default(0);
        });
    }
};
