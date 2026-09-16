<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->time('timeIn')->nullable()->after('remarks');
            $table->time('timeOut')->nullable()->after('timeIn');
            $table->boolean('isLocked')->default(false)->after('timeOut');
            $table->boolean('isDraft')->default(true)->after('isLocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['timeIn', 'timeOut', 'isLocked', 'isDraft']);
        });
    }
};
