<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Subject is fixed per classroom: backfill legacy nulls and keep
     * enforcing required-on-create + immutable-on-update at the app layer
     * (validation + policy), so no disruptive column rebuild is needed.
     */
    public function up(): void
    {
        DB::table('classrooms')
            ->whereNull('subject')
            ->orWhere('subject', '')
            ->update(['subject' => 'General']);
    }

    public function down(): void
    {
        // No-op: backfill is not reversible.
    }
};
