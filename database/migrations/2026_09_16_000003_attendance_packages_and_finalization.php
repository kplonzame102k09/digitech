<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Advisory attendance flow:
     *  - `attendance_packages` is one adviser's whole-day submission for their
     *    advisees: the adviser reviews every classroom's marks, edits a single
     *    official final per student, SUBMITs the day to the admin, and the
     *    admin FINALIZE locks it (status `submitted` -> `finalized`).
     *  - `attendance.finalizedAt / finalizedBy` stamp each official final row
     *    when its package is finalized. Only finalized finals count in
     *    analytics and surface to student/parent views.
     */
    public function up(): void
    {
        if (! Schema::hasTable('attendance_packages')) {
            Schema::create('attendance_packages', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('adviserId');
                $table->date('date');
                $table->string('status', 16)->default('submitted')->index();
                $table->timestamp('submittedAt')->nullable();
                $table->string('submittedBy')->nullable();
                $table->timestamp('finalizedAt')->nullable();
                $table->string('finalizedBy')->nullable();
                $table->timestamps();

                $table->unique(['adviserId', 'date'], 'attendance_packages_adviser_date_unique');
                $table->index('adviserId', 'attendance_packages_adviser_index');
                $table->foreign('adviserId')->references('user_id')->on('users')->cascadeOnDelete();
            });
        }

        Schema::table('attendance', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance', 'finalizedAt')) {
                $table->timestamp('finalizedAt')->nullable()->after('verifiedAt');
            }
            if (! Schema::hasColumn('attendance', 'finalizedBy')) {
                $table->string('finalizedBy')->nullable()->after('finalizedAt');
            }
            if (Schema::hasColumn('attendance', 'finalizedAt') && ! Schema::hasIndex('attendance', 'attendance_finalizedat_index')) {
                $table->index('finalizedAt', 'attendance_finalizedat_index');
            }
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('attendance_packages')) {
            Schema::dropIfExists('attendance_packages');
        }

        Schema::table('attendance', function (Blueprint $table): void {
            if (Schema::hasIndex('attendance', 'attendance_finalizedat_index')) {
                $table->dropIndex('attendance_finalizedat_index');
            }
            foreach (['finalizedAt', 'finalizedBy'] as $column) {
                if (Schema::hasColumn('attendance', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::enableForeignKeyConstraints();
    }
};
