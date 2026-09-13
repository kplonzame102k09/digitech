<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_activities', function (Blueprint $table): void {
            $table->string('term', 20)->default('Prelim')->after('description');
            $table->index(['classroom_id', 'term']);
        });

        // Backfill legacy rows (created before term tagging) as Prelim work.
        DB::table('classroom_activities')
            ->whereNull('term')
            ->orWhere('term', '')
            ->update(['term' => 'Prelim']);
    }

    public function down(): void
    {
        Schema::table('classroom_activities', function (Blueprint $table): void {
            $table->dropIndex(['classroom_id', 'term']);
            $table->dropColumn('term');
        });
    }
};
