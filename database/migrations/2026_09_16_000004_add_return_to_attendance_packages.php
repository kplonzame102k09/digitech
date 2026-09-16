<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RETURN step of the advisory attendance flow: an admin may send a
     * submitted package back to its adviser for correction (`status` becomes
     * `returned`). The adviser re-edits and resubmits (-> `submitted` again,
     * which clears these fields); returnedAt/By/Reason record the last return.
     */
    public function up(): void
    {
        Schema::table('attendance_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance_packages', 'returnedAt')) {
                $table->timestamp('returnedAt')->nullable()->after('finalizedBy');
            }
            if (! Schema::hasColumn('attendance_packages', 'returnedBy')) {
                $table->string('returnedBy')->nullable()->after('returnedAt');
            }
            if (! Schema::hasColumn('attendance_packages', 'returnReason')) {
                $table->string('returnReason')->nullable()->after('returnedBy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_packages', function (Blueprint $table): void {
            foreach (['returnedAt', 'returnedBy', 'returnReason'] as $column) {
                if (Schema::hasColumn('attendance_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};