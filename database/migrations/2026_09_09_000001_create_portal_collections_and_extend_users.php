<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_collections', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->after('role');
            $table->string('username')->nullable()->after('email');
            $table->text('photo')->nullable()->after('rolePassword');
            $table->string('strand')->nullable()->after('photo');
            $table->string('address')->nullable()->after('strand');
            $table->string('childId')->nullable()->after('address');
            $table->json('childIds')->nullable()->after('childId');
            $table->string('guardianName')->nullable()->after('childIds');
            $table->string('guardianContact')->nullable()->after('guardianName');
            $table->boolean('mustChangePassword')->default(false)->after('guardianContact');
            $table->string('employeeId')->nullable()->after('mustChangePassword');
            $table->string('department')->nullable()->after('employeeId');
            $table->json('profile_extra')->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'username',
                'photo',
                'strand',
                'address',
                'childId',
                'childIds',
                'guardianName',
                'guardianContact',
                'mustChangePassword',
                'employeeId',
                'department',
                'profile_extra',
            ]);
        });

        Schema::dropIfExists('portal_collections');
    }
};
