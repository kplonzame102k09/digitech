<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_sync_recomputes_weighted_final_grade(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin', 'user_id' => 'ADMIN-000001']);
        $grade = Grade::factory()->create([
            'studentId' => $student->user_id,
            'published' => false,
        ]);

        $response = $this->actingAs($admin)->putJson('/api/portal/grades', [
            'value' => [
                [
                    'id' => $grade->id,
                    'studentId' => $student->user_id,
                    'subject' => $grade->subject,
                    'semester' => '1st Semester',
                    'prelim' => 85,
                    'midterm' => 90,
                    'finals' => 95,
                    'units' => 3,
                    'schoolYear' => '2026-2027',
                    'published' => true,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'prelim' => 85,
            'midterm' => 90,
            'finals' => 95,
            'finalGrade' => round((85 * 0.20) + (90 * 0.30) + (95 * 0.50), 2),
        ]);
    }

    public function test_student_summary_returns_averages_and_gwa(): void
    {
        Grade::query()->delete();

        $student = User::factory()->create(['role' => 'student']);

        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'Mathematics',
            'semester' => Grade::SEMESTER_FIRST,
            'prelim' => 85, 'midterm' => 90, 'finals' => 95,
            'units' => 3,
            'published' => true,
        ]);
        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'English',
            'semester' => Grade::SEMESTER_FIRST,
            'prelim' => 80, 'midterm' => 82, 'finals' => 84,
            'units' => 2,
            'published' => true,
        ]);

        $response = $this->actingAs($student)->getJson('/student/api/grades/summary?schoolYear=2026-2027');

        $response->assertOk()->assertJsonPath('generalAverage', (float) round(((91.5 * 3) + (82.6 * 2)) / 5, 2));
        $this->assertArrayHasKey('annual', $response->json());
    }

    public function test_student_only_sees_published_grades(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'Mathematics',
            'published' => false,
        ]);
        Grade::factory()->create([
            'studentId' => $student->user_id,
            'subject' => 'English',
            'published' => true,
        ]);

        $response = $this->actingAs($student)->getJson('/student/api/grades');

        $response->assertOk();
        $this->assertCount(1, $response->json('grades'));
        $this->assertSame('English', $response->json('grades.0.subject'));
    }

    public function test_mixed_role_cannot_read_grades(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($teacher)->getJson('/student/api/grades');

        $response->assertForbidden();
    }
}
