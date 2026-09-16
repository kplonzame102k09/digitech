<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance funnel fields:
     *  - `classrooms.academicYear / department / section / startTime / endTime`
     *    let a teacher drill down Academic Year -> Department -> Section ->
     *    Subject before picking a classroom (Subject + Section + Teacher +
     *    Schedule). Times also drive the automatic overlap conflict check and
     *    the scheduled-end auto-submit.
     *  - `attendance_sessions` is one marked run (date + start/end + status).
     *  - `attendance.sessionId / requiresVerification / verificationStatus /
     *    verifiedBy / verifiedAt` link session marks to their session and hold
     *    the "multiple/conflict -> verification required" state.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            if (! Schema::hasColumn('classrooms', 'academicYear')) {
                $table->string('academicYear', 32)->nullable()->after('subject');
            }
            if (! Schema::hasColumn('classrooms', 'department')) {
                $table->string('department', 64)->nullable()->after('academicYear');
            }
            if (! Schema::hasColumn('classrooms', 'section')) {
                $table->string('section', 64)->nullable()->after('department');
            }
            if (! Schema::hasColumn('classrooms', 'startTime')) {
                $table->time('startTime')->nullable()->after('section');
            }
            if (! Schema::hasColumn('classrooms', 'endTime')) {
                $table->time('endTime')->nullable()->after('startTime');
            }
        });

        if (! Schema::hasTable('attendance_sessions')) {
            Schema::create('attendance_sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('classroomId');
                $table->date('date');
                $table->time('startTime');
                $table->time('scheduledEndTime')->nullable();
                $table->time('actualEndTime')->nullable();
                $table->string('status', 16)->default('open')->index();
                $table->boolean('autoSubmit')->default(true);
                $table->string('startedBy')->nullable();
                $table->string('lockedBy')->nullable();
                $table->timestamp('lockedAt')->nullable();
                $table->timestamps();

                $table->index('classroomId', 'attendance_sessions_classroom_index');
                $table->foreign('classroomId')->references('id')->on('classrooms')->cascadeOnDelete();
            });
        }

        Schema::table('attendance', function (Blueprint $table): void {
            // A verification can be recorded before the class marks the status,
            // so `status` has to allow a not-yet-marked row.
            if (Schema::hasColumn('attendance', 'status')) {
                $table->string('status')->nullable()->change();
            }
            if (! Schema::hasColumn('attendance', 'sessionId')) {
                $table->string('sessionId')->nullable()->after('classroomName');
            }
            if (Schema::hasColumn('attendance', 'sessionId') && ! Schema::hasIndex('attendance', 'attendance_sessionid_index')) {
                $table->index('sessionId', 'attendance_sessionid_index');
            }
            if (! Schema::hasColumn('attendance', 'requiresVerification')) {
                $table->boolean('requiresVerification')->default(false)->after('isDraft');
            }
            if (! Schema::hasColumn('attendance', 'verificationStatus')) {
                $table->string('verificationStatus', 16)->nullable()->after('requiresVerification');
            }
            if (! Schema::hasColumn('attendance', 'verifiedBy')) {
                $table->string('verifiedBy')->nullable()->after('verificationStatus');
            }
            if (! Schema::hasColumn('attendance', 'verifiedAt')) {
                $table->timestamp('verifiedAt')->nullable()->after('verifiedBy');
            }
        });

        // Backfill the funnel fields for existing classrooms so the filters
        // never return an empty picker before a teacher fills them in.
        $year = now()->year;
        $schoolYear = $year.'-'.($year + 1);

        DB::table('classrooms')
            ->whereNull('section')
            ->orWhere('section', '')
            ->update(['section' => DB::raw('`name`')]);

        DB::table('classrooms')
            ->whereNull('academicYear')
            ->orWhere('academicYear', '')
            ->update(['academicYear' => $schoolYear]);

        $departments = DB::table('classrooms')
            ->join('users', 'users.user_id', '=', 'classrooms.teacherId')
            ->where(function ($q): void {
                $q->whereNull('classrooms.department')->orWhere('classrooms.department', '');
            })
            ->whereNotNull('users.department')
            ->select(['classrooms.id', 'users.department'])
            ->get();

        foreach ($departments as $row) {
            DB::table('classrooms')
                ->where('id', $row->id)
                ->update(['department' => $row->department]);
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('attendance_sessions')) {
            Schema::dropIfExists('attendance_sessions');
        }

        Schema::table('attendance', function (Blueprint $table): void {
            foreach (['sessionId', 'requiresVerification', 'verificationStatus', 'verifiedBy', 'verifiedAt'] as $column) {
                if (! Schema::hasColumn('attendance', $column)) {
                    continue;
                }
                if ($column === 'sessionId' && Schema::hasIndex('attendance', 'attendance_sessionid_index')) {
                    $table->dropIndex('attendance_sessionid_index');
                }
                $table->dropColumn($column);
            }
            if (Schema::hasColumn('attendance', 'status')) {
                $table->string('status')->nullable(false)->change();
            }
        });

        Schema::table('classrooms', function (Blueprint $table): void {
            foreach (['academicYear', 'department', 'section', 'startTime', 'endTime'] as $column) {
                if (Schema::hasColumn('classrooms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::enableForeignKeyConstraints();
    }
};
