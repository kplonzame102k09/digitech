<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->string('semester')->default('1st Semester')->after('subject');
            $table->decimal('prelim', 5, 2)->nullable()->after('semester');
            $table->decimal('midterm', 5, 2)->nullable()->after('prelim');
            $table->decimal('finals', 5, 2)->nullable()->after('midterm');
            $table->decimal('finalGrade', 5, 2)->nullable()->after('finals');
            $table->decimal('units', 4, 2)->default(1.00)->after('finalGrade');
            $table->string('schoolYear')->nullable()->after('units');
        });

        // Migrate existing grade + term data into new columns
        $rows = DB::table('grades')->select('id', 'grade', 'term', 'period')->get();

        foreach ($rows as $row) {
            $updates = [];

            if ($row->term !== null) {
                $termLower = strtolower(trim($row->term));

                if (str_contains($termLower, 'prelim')) {
                    $updates['prelim'] = $row->grade;
                } elseif (str_contains($termLower, 'midterm') || str_contains($termLower, 'mid')) {
                    $updates['midterm'] = $row->grade;
                } elseif (str_contains($termLower, 'final') || str_contains($termLower, 'finals')) {
                    $updates['finals'] = $row->grade;
                }

                if (str_contains($termLower, '2') || str_contains($termLower, 'second')) {
                    $updates['semester'] = '2nd Semester';
                }
            }

            if ($updates !== []) {
                DB::table('grades')->where('id', $row->id)->update($updates);
            }
        }

        // Compute finalGrade from the three term grades
        DB::statement('
            UPDATE grades
            SET finalGrade = ROUND(
                (COALESCE(prelim, 0) * 0.20) +
                (COALESCE(midterm, 0) * 0.30) +
                (COALESCE(finals, 0) * 0.50),
                2
            )
            WHERE prelim IS NOT NULL OR midterm IS NOT NULL OR finals IS NOT NULL
        ');

        // Copy existing grade column into finalGrade where new columns are empty
        DB::statement('
            UPDATE grades
            SET finalGrade = grade
            WHERE finalGrade IS NULL AND grade IS NOT NULL
        ');

        Schema::table('grades', function (Blueprint $table): void {
            $table->dropColumn(['term', 'period']);
        });

        DB::statement('CREATE UNIQUE INDEX grades_student_subject_semester_unique ON grades (studentId, subject(100), schoolYear, semester)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX grades_student_subject_semester_unique ON grades');

        Schema::table('grades', function (Blueprint $table): void {
            $table->dropColumn(['semester', 'prelim', 'midterm', 'finals', 'finalGrade', 'units', 'schoolYear']);
        });
    }
};
