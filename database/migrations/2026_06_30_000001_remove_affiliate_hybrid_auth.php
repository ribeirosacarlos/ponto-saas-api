<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Removes the hybrid "affiliate-as-User" auth path.
// Precondition: confirm no rows with user_id IS NOT NULL exist in commercial_affiliates before deploying.
// Query: SELECT id, email FROM <affiliates_table> WHERE user_id IS NOT NULL;
return new class extends Migration
{
    public function up(): void
    {
        // Make user_id nullable on lead_notes and lead_step_logs so affiliate portal
        // can create notes and step logs without a linked User account.
        Schema::table(CommercialSchema::table('lead_notes'), function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
        });

        Schema::table(CommercialSchema::table('lead_step_logs'), function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
        });

        // Drop user_id FK and column from commercial_affiliates.
        Schema::table(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        // Remove affiliate role (no longer used after hybrid path removal).
        $role = DB::table('roles')->where('name', 'affiliate')->first();

        if ($role) {
            DB::table('role_user')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on(CommercialSchema::usersTable())->nullOnDelete();
        });

        Schema::table(CommercialSchema::table('lead_notes'), function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
        });

        Schema::table(CommercialSchema::table('lead_step_logs'), function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
        });
    }
};
