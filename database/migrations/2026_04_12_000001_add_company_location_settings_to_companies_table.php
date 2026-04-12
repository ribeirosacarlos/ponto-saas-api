<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('company_latitude', 10, 7)->nullable()->after('geolocation_required');
            $table->decimal('company_longitude', 10, 7)->nullable()->after('company_latitude');
            $table->unsignedInteger('allowed_radius_meters')->default(100)->after('company_longitude');
            $table->boolean('location_validation_enabled')->default(false)->after('allowed_radius_meters');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'company_latitude',
                'company_longitude',
                'allowed_radius_meters',
                'location_validation_enabled',
            ]);
        });
    }
};
