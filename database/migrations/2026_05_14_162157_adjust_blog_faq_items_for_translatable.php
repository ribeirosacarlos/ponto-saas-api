<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Postgres only: change column storage type to native JSON.
        // SQLite stores any value in any column (no type enforcement), so this is a no-op there.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "question" TYPE json USING "question"::json');
            DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "answer" TYPE json USING "answer"::json');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "question" TYPE text USING "question"::text');
            DB::statement('ALTER TABLE blog_faq_items ALTER COLUMN "answer" TYPE text USING "answer"::text');
        }
    }
};
