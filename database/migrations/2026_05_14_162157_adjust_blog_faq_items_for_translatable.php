<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "question" TYPE json USING "question"::json');
        DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "answer" TYPE json USING "answer"::json');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "question" TYPE text USING "question"::text');
        DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "answer" TYPE text USING "answer"::text');
    }
};
