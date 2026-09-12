<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy schema cleanup from the term-grading restructure:
     *  - `grades.grade` held a single numeric grade before prelim/midterm/finals
     *    landed; the live data was already migrated into `finalGrade`, so the
     *    dead column is removed.
     *  - `attendance` is recorded per-subject, but its uniqueness was still
     *    (studentId, date), which would reject a second subject's record on the
     *    same day. The unique key now spans (studentId, date, subject).
     */
    public function up(): void
    {
        if (Schema::hasColumn('grades', 'grade')) {
            Schema::table('grades', function (Blueprint $table): void {
                $table->dropColumn('grade');
            });
        }

        if (Schema::hasIndex('attendance', 'attendance_studentid_date_unique')) {
            // MySQL InnoDB requires a backing index for the FK constraint; the
            // composite unique currently serves that purpose. Add a simple
            // support index first, then safely drop the unique.
            if (! Schema::hasIndex('attendance', 'attendance_studentid_index')) {
                Schema::table('attendance', function (Blueprint $table): void {
                    $table->index('studentId');
                });
            }

            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropUnique('attendance_studentid_date_unique');
            });

            Schema::table('attendance', function (Blueprint $table): void {
                $table->unique(['studentId', 'date', 'subject'], 'attendance_studentid_date_subject_unique');
            });

            // The temporary support index is no longer needed after the new
            // composite unique covers (studentId, date, subject).
            if (Schema::hasIndex('attendance', 'attendance_studentid_index')) {
                Schema::table('attendance', function (Blueprint $table): void {
                    $table->dropIndex('attendance_studentid_index');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->decimal('grade', 5, 2)->nullable()->after('finalGrade');
        });

        Schema::table('attendance', function (Blueprint $table): void {
            $table->dropUnique('attendance_studentid_date_subject_unique');
        });

        Schema::table('attendance', function (Blueprint $table): void {
            $table->unique(['studentId', 'date'], 'attendance_studentid_date_unique');
        });
    }
};
