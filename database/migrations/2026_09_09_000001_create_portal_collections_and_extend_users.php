<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_collections')) {
            Schema::create('portal_collections', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->json('value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = [
            'status' => fn (Blueprint $table) => $table->string('status')->default('active'),
            'username' => fn (Blueprint $table) => $table->string('username')->nullable(),
            'photo' => fn (Blueprint $table) => $table->text('photo')->nullable(),
            'strand' => fn (Blueprint $table) => $table->string('strand')->nullable(),
            'address' => fn (Blueprint $table) => $table->string('address')->nullable(),
            'childId' => fn (Blueprint $table) => $table->string('childId')->nullable(),
            'childIds' => fn (Blueprint $table) => $table->json('childIds')->nullable(),
            'guardianName' => fn (Blueprint $table) => $table->string('guardianName')->nullable(),
            'guardianContact' => fn (Blueprint $table) => $table->string('guardianContact')->nullable(),
            'mustChangePassword' => fn (Blueprint $table) => $table->boolean('mustChangePassword')->default(false),
            'employeeId' => fn (Blueprint $table) => $table->string('employeeId')->nullable(),
            'department' => fn (Blueprint $table) => $table->string('department')->nullable(),
            'profile_extra' => fn (Blueprint $table) => $table->json('profile_extra')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (Schema::hasColumn('users', $name)) {
                continue;
            }

            Schema::table('users', $definition);
        }
    }

    public function down(): void
    {
        // This migration may have completed against an existing production schema.
        // Do not remove user data or columns during an independent rollback.
    }
};
