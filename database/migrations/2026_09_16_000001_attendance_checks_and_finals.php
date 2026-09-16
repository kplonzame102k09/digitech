<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-layer attendance:
     *  - `attendance.kind = 'check'`: one per student/day/classroom, written
     *    by that classroom's owner teacher (raw presence marks).
     *  - `attendance.kind = 'final'`: one per student/day, written only by
     *    the student's assigned teacher (official submitted record).
     *  - `attendance.classroomName` denormalizes the classroom name so the
     *    admin details modal needs no extra endpoint.
     *
     * Legacy rows predate `kind` and were the official record, so they are
     * backfilled as finals. `classroomId` NULLs become '' because MySQL
     * ignores NULLs in unique keys (which would allow duplicate finals).
     */
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance', 'kind')) {
                $table->string('kind', 16)->default('final')->after('session');
            }
            if (! Schema::hasColumn('attendance', 'classroomName')) {
                $table->string('classroomName')->nullable()->after('classroomId');
            }
        });

        // Backfill legacy rows as official finals.
        DB::table('attendance')->whereNull('kind')->update(['kind' => 'final']);
        DB::table('attendance')->whereNull('classroomId')->update(['classroomId' => '']);

        // Collapse duplicates that NULL classroomIds used to allow: keep the
        // newest row per (student, date, subject, classroom, session, kind).
        $dupes = DB::table('attendance')
            ->select('studentId', 'date', 'subject', 'classroomId', 'session', 'kind', DB::raw('COUNT(*) AS c'), DB::raw('MAX(updated_at) AS newest'))
            ->groupBy('studentId', 'date', 'subject', 'classroomId', 'session', 'kind')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            $ids = DB::table('attendance')
                ->where('studentId', $dupe->studentId)
                ->whereDate('date', $dupe->date)
                ->where('subject', $dupe->subject)
                ->where('classroomId', $dupe->classroomId)
                ->where('session', $dupe->session)
                ->where('kind', $dupe->kind)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id')
                ->all();
            array_shift($ids); // keep newest
            if ($ids !== []) {
                DB::table('attendance')->whereIn('id', $ids)->delete();
            }
        }

        if (! Schema::hasColumn('attendance', 'kind')) {
            return;
        }

        DB::statement('ALTER TABLE `attendance` MODIFY `classroomId` VARCHAR(255) NOT NULL DEFAULT ""');
        DB::statement("ALTER TABLE `attendance` MODIFY `kind` VARCHAR(16) NOT NULL DEFAULT 'final'");

        // The studentId FK leans on the leftmost prefix of the old composite
        // unique, so InnoDB refuses to drop it until another studentId index
        // exists. Create replacements first, then drop the old unique.
        if (! Schema::hasIndex('attendance', 'attendance_studentid_index')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->index('studentId', 'attendance_studentid_index');
            });
        }

        Schema::table('attendance', function (Blueprint $table): void {
            $table->unique(
                ['studentId', 'date', 'classroomId', 'session', 'kind'],
                'attendance_student_date_class_session_kind_unique'
            );
            $table->index('kind', 'attendance_kind_index');
        });

        if (Schema::hasIndex('attendance', 'attendance_student_date_subject_class_session_unique')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropUnique('attendance_student_date_subject_class_session_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('attendance', 'attendance_student_date_class_session_kind_unique')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropUnique('attendance_student_date_class_session_kind_unique');
            });
        }
        if (Schema::hasIndex('attendance', 'attendance_kind_index')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropIndex('attendance_kind_index');
            });
        }

        // Keep attendance_studentid_index: the studentId FK needs a backing
        // index and InnoDB will reuse it.
        if (! Schema::hasIndex('attendance', 'attendance_student_date_subject_class_session_unique')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->unique(
                    ['studentId', 'date', 'subject', 'classroomId', 'session'],
                    'attendance_student_date_subject_class_session_unique'
                );
            });
        }

        DB::statement('ALTER TABLE `attendance` MODIFY `classroomId` VARCHAR(255) NULL');
    }
};
