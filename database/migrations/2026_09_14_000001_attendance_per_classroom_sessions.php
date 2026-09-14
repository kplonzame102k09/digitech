<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-classroom attendance with daily sessions:
     *  - `attendance.classroomId` separates records per classroom even when
     *    two classrooms share a subject (nullable so legacy rows keep working).
     *  - `attendance.session` is the 1..N slot of the day (default 1, so old
     *    rows and single-mark flows are session 1).
     *  - `classrooms.sessionsPerDay` is the teacher-set expected session
     *    count used for the daily percentage (default 1).
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->unsignedTinyInteger('sessionsPerDay')->default(1)->after('subject');
        });

        Schema::table('attendance', function (Blueprint $table): void {
            $table->string('classroomId')->nullable()->after('subject');
            $table->unsignedTinyInteger('session')->default(1)->after('classroomId');
            $table->index('classroomId', 'attendance_classroomid_index');
        });

        if (Schema::hasIndex('attendance', 'attendance_studentid_date_subject_unique')) {
            // Keep a plain studentId index around: InnoDB wants backing for
            // the FK while the composite unique is being replaced.
            if (! Schema::hasIndex('attendance', 'attendance_studentid_index')) {
                Schema::table('attendance', function (Blueprint $table): void {
                    $table->index('studentId');
                });
            }

            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropUnique('attendance_studentid_date_subject_unique');
            });

            Schema::table('attendance', function (Blueprint $table): void {
                $table->unique(
                    ['studentId', 'date', 'subject', 'classroomId', 'session'],
                    'attendance_student_date_subject_class_session_unique'
                );
            });

            if (Schema::hasIndex('attendance', 'attendance_studentid_index')) {
                Schema::table('attendance', function (Blueprint $table): void {
                    $table->dropIndex('attendance_studentid_index');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('attendance', 'attendance_student_date_subject_class_session_unique')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropUnique('attendance_student_date_subject_class_session_unique');
            });

            Schema::table('attendance', function (Blueprint $table): void {
                $table->unique(['studentId', 'date', 'subject'], 'attendance_studentid_date_subject_unique');
            });
        }

        Schema::table('attendance', function (Blueprint $table): void {
            $table->dropIndex('attendance_classroomid_index');
            $table->dropColumn(['session', 'classroomId']);
        });

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropColumn('sessionsPerDay');
        });
    }
};
