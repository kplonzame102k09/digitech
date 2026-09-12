<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->json('programs')->nullable()->after('enrollmentDeadline');
            $table->json('tvetQualifications')->nullable()->after('programs');
            $table->json('tvetLevels')->nullable()->after('tvetQualifications');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropColumn(['programs', 'tvetQualifications', 'tvetLevels']);
        });
    }
};
