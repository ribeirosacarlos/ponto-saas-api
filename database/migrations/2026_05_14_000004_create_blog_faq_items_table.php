<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_faq_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->unsignedInteger('order_index')->default(0);

            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_faq_items');
    }
};
