<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'timezone')) {
                $table->string('timezone')->nullable()->after('state');
            }

            if (! Schema::hasColumn('companies', 'country')) {
                $table->string('country')->nullable()->after('timezone');
            }

            if (! Schema::hasColumn('companies', 'locale')) {
                $table->string('locale')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'locale')) {
                $table->dropColumn('locale');
            }

            if (Schema::hasColumn('companies', 'country')) {
                $table->dropColumn('country');
            }

            if (Schema::hasColumn('companies', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
