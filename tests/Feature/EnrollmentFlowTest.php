<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_change_status_to_enrolled_via_update(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'status' => 'Draft',
            'schoolYear' => '2026-2027',
        ]);

        $response = $this->actingAs($student)
            ->putJson("/student/api/enrollments/{$enrollment->id}", ['status' => 'Enrolled']);

        $response->assertStatus(422);
    }

    public function test_student_cannot_downgrade_an_approved_enrollment(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'status' => 'Enrolled',
            'schoolYear' => '2026-2027',
        ]);

        $response = $this->actingAs($student)
            ->putJson("/student/api/enrollments/{$enrollment->id}", ['status' => 'Submitted']);

        $response->assertOk();
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => 'Enrolled',
        ]);
    }

    public function test_student_cannot_create_duplicate_enrollment_for_same_school_year(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'schoolYear' => '2026-2027',
        ]);

        $response = $this->actingAs($student)->postJson('/student/api/enrollments', [
            'programType' => 'Senior High',
            'gradeLevel' => 'Grade 12',
            'strand' => 'ICT',
            'schoolYear' => '2026-2027',
            'contact' => '09123456789',
            'birthDate' => '2008-01-01',
            'address' => '123 Test St',
            'guardianName' => 'Parent Name',
            'guardianContact' => '09999999999',
        ]);

        $response->assertStatus(422);
    }

    public function test_student_can_update_enrollment_contact_details(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'contact' => '09111111111',
        ]);
        $enrollment = Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'status' => 'Draft',
            'schoolYear' => '2026-2027',
        ]);

        $response = $this->actingAs($student)
            ->putJson("/student/api/enrollments/{$enrollment->id}", ['contact' => '09222222222']);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'contact' => '09222222222',
        ]);
    }
}
