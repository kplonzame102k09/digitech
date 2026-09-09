<?php

namespace Tests\Unit;

use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_grade_is_weighted_from_term_grades(): void
    {
        $grade = new Grade([
            'prelim' => 85.0,
            'midterm' => 90.0,
            'finals' => 95.0,
        ]);

        $this->assertSame(91.5, $grade->recalculateFinalGrade());
    }

    public function test_final_grade_is_null_when_no_term_grades(): void
    {
        $grade = new Grade(['prelim' => null, 'midterm' => null, 'finals' => null]);

        $this->assertNull($grade->recalculateFinalGrade());
    }

    public function test_general_average_is_weighted_by_units(): void
    {
        $grades = collect([
            new Grade(['finalGrade' => 80.0, 'units' => 3]),
            new Grade(['finalGrade' => 90.0, 'units' => 2]),
            new Grade(['finalGrade' => 95.0, 'units' => 1]),
        ]);

        $expected = round((80 * 3 + 90 * 2 + 95 * 1) / 6, 2);

        $this->assertSame($expected, Grade::generalAverage($grades));
    }

    #[DataProvider('gwaScaleProvider')]
    public function test_gwa_conversion_follows_ched_scale(float $average, float $expectedGwa): void
    {
        $this->assertSame($expectedGwa, Grade::convertToGwa($average));
    }

    public static function gwaScaleProvider(): array
    {
        return [
            [100.0, 1.00],
            [97.5, 1.00],
            [96.0, 1.25],
            [94.0, 1.25],
            [91.5, 1.50],
            [88.0, 1.75],
            [85.0, 2.00],
            [82.0, 2.25],
            [79.0, 2.50],
            [76.0, 2.75],
            [75.0, 3.00],
            [74.9, 5.00],
        ];
    }

    public function test_published_duplicate_subject_conflicts_for_same_year_and_semester(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'Mathematics',
            'schoolYear' => '2026-2027',
            'semester' => Grade::SEMESTER_FIRST,
        ]);

        $this->expectException(QueryException::class);

        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'Mathematics',
            'schoolYear' => '2026-2027',
            'semester' => Grade::SEMESTER_FIRST,
        ]);
    }

    public function test_same_subject_allowed_across_semesters(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'Mathematics',
            'schoolYear' => '2026-2027',
            'semester' => Grade::SEMESTER_FIRST,
        ]);

        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'Mathematics',
            'schoolYear' => '2026-2027',
            'semester' => Grade::SEMESTER_SECOND,
        ]);

        $this->assertDatabaseCount('grades', 2);
    }
}
