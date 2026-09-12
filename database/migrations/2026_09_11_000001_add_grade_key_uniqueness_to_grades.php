<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce one grade per (studentId, subject, schoolYear, semester) with a
     * fixed-width hash column. The prior attempt used a MySQL-only `subject(100)`
     * prefix index whose key length exceeds the InnoDB 3072-byte limit under
     * utf8mb4. A single char(32) unique key is portable and index-safe on both
     * MySQL and SQLite.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->char('gradeKey', 32)->nullable()->after('subject');
        });

        foreach (DB::table('grades')->select('id', 'studentId', 'subject', 'schoolYear', 'semester')->get() as $row) {
            DB::table('grades')->where('id', $row->id)->update([
                'gradeKey' => md5(implode('|', [$row->studentId, $row->subject, $row->schoolYear, $row->semester])),
            ]);
        }

        Schema::table('grades', function (Blueprint $table): void {
            $table->unique('gradeKey', 'grades_grade_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->dropUnique('grades_grade_key_unique');
            $table->dropColumn('gradeKey');
        });
    }
};
