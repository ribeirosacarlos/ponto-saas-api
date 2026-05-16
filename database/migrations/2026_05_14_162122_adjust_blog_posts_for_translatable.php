<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop all indexes referencing 'language' before dropping the column.
        // SQLite rebuilds indexes after column drops and fails if the index references the dropped column.
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex('blog_posts_language_index');
            $table->dropIndex('blog_posts_status_language_category_index');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('language');
        });

        // Postgres only: change column storage type to native JSON.
        // SQLite stores any value in any column (no type enforcement), so this is a no-op there.
        if (DB::getDriverName() === 'pgsql') {
            $textToJson = ['title', 'excerpt', 'content_html', 'seo_title', 'seo_description', 'hero_image_alt', 'hero_caption'];
            foreach ($textToJson as $col) {
                DB::statement("ALTER TABLE blog_posts ALTER COLUMN \"{$col}\" TYPE json USING \"{$col}\"::json");
            }
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

        if (DB::getDriverName() === 'pgsql') {
            $jsonToText = ['title', 'excerpt', 'content_html', 'seo_title', 'seo_description', 'hero_image_alt', 'hero_caption'];
            foreach ($jsonToText as $col) {
                DB::statement("ALTER TABLE blog_posts ALTER COLUMN \"{$col}\" TYPE text USING \"{$col}\"::text");
            }
        }

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->index('language');
            $table->index(['status', 'language', 'category']);
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
