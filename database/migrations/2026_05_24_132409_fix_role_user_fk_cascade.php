<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE "role_user" DROP CONSTRAINT IF EXISTS "role_user_user_id_foreign"');
        DB::statement('ALTER TABLE "role_user" ADD CONSTRAINT "role_user_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE "role_user" DROP CONSTRAINT IF EXISTS "role_user_user_id_foreign"');
        DB::statement('ALTER TABLE "role_user" ADD CONSTRAINT "role_user_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE RESTRICT');
    }
};
