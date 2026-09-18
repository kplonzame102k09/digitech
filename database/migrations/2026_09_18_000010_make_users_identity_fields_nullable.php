<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = [
            'birthDate' => 'date',
            'birthPlace' => 'string',
            'barangay' => 'string',
            'city' => 'string',
            'province' => 'string',
            'region' => 'string',
        ];

        foreach ($columns as $column => $type) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column, $type) {
                $table->{$type}($column)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        // Intentionally no-op: existing rows may now contain NULL values and
        // restoring NOT NULL constraints could fail or discard real data.
    }
};