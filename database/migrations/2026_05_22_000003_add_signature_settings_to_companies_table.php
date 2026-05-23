<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('enable_native_signatures')->default(false)->after('allow_desktop_clock');
            $table->boolean('require_timesheet_signature')->default(false)->after('enable_native_signatures');
            $table->boolean('require_password_confirmation_for_signature')->default(true)->after('require_timesheet_signature');
            $table->boolean('allow_geolocation_on_signature')->default(false)->after('require_password_confirmation_for_signature');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'enable_native_signatures',
                'require_timesheet_signature',
                'require_password_confirmation_for_signature',
                'allow_geolocation_on_signature',
            ]);
        });
    }
};
