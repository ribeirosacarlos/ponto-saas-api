<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_signatures', function (Blueprint $table) {
            $table->string('signature_image_path', 500)->nullable()->after('user_agent');
            $table->string('document_hash', 64)->nullable()->after('signature_image_path');
            $table->string('signature_hash', 64)->nullable()->after('document_hash');
            $table->decimal('latitude', 10, 7)->nullable()->after('signature_hash');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('accepted_terms')->default(false)->after('longitude');
            $table->timestamp('password_confirmed_at')->nullable()->after('accepted_terms');
            $table->timestamp('superseded_at')->nullable()->after('password_confirmed_at');
            $table->json('metadata')->nullable()->after('superseded_at');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_signatures', function (Blueprint $table) {
            $table->dropColumn([
                'signature_image_path',
                'document_hash',
                'signature_hash',
                'latitude',
                'longitude',
                'accepted_terms',
                'password_confirmed_at',
                'superseded_at',
                'metadata',
            ]);
        });
    }
};
