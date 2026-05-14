<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_related_posts', function (Blueprint $table) {
            $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignUuid('related_post_id')->constrained('blog_posts')->cascadeOnDelete();

            $table->primary(['post_id', 'related_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_related_posts');
    }
};
