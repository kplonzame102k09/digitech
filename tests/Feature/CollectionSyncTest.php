<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_promote_their_enrollment_status_through_the_collection_facade(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'status' => 'Draft',
            'schoolYear' => '2026-2027',
        ]);

        $payload = $this->enrollmentPayload($enrollment, 'Enrolled');

        $response = $this->actingAs($student)
            ->putJson('/api/portal/enrollments', ['value' => $payload]);

        $response->assertOk();
        $this->assertContains($response->json('value.0.status'), ['Draft', 'Submitted']);
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => 'Draft',
        ]);
    }

    public function test_admin_can_approve_an_enrollment_and_stamps_review_fields(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin', 'user_id' => 'ADMIN-000001']);
        $enrollment = Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'status' => 'Submitted',
            'schoolYear' => '2026-2027',
        ]);

        $response = $this->actingAs($admin)
            ->putJson('/api/portal/enrollments', ['value' => $this->enrollmentPayload($enrollment, 'Enrolled')]);

        $response->assertOk();
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => 'Enrolled',
            'reviewedBy' => 'ADMIN-000001',
        ]);
        $this->assertNotNull($response->json('value.0.reviewedAt'));
    }

    public function test_collection_write_policies_are_enforced_per_role(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $parent = User::factory()->create(['role' => 'parent']);

        $this->actingAs($teacher)->putJson('/api/portal/documentRequests', ['value' => [[]]])->assertForbidden();
        $this->actingAs($student)->putJson('/api/portal/announcements', ['value' => [[]]])->assertForbidden();
        $this->actingAs($parent)->putJson('/api/portal/enrollments', ['value' => [[]]])->assertForbidden();

        $this->actingAs($teacher)->putJson('/api/portal/announcements', ['value' => [[]]])->assertOk();
        $this->actingAs($parent)->putJson('/api/portal/notifications', ['value' => [[]]])->assertOk();
    }

    public function test_non_admin_cannot_import_users(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->postJson('/api/portal/users/import', ['users' => [['id' => 'TCH-TEST-001']]])
            ->assertForbidden();
    }

    public function test_boot_payload_exposes_table_backed_collections_for_the_user(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'status' => 'Draft',
            'schoolYear' => '2026-2027',
        ]);

        $response = $this->actingAs($student)->getJson('/api/portal/boot');

        $response->assertOk();
        $response->assertJsonPath('currentUser.id', $student->user_id);
        $this->assertCount(1, $response->json('collections.enrollments'));
    }

    private function enrollmentPayload(Enrollment $enrollment, string $status): array
    {
        return [[
            'id' => $enrollment->id,
            'studentId' => $enrollment->studentId,
            'status' => $status,
            'programType' => 'Senior High',
            'gradeLevel' => 'Grade 12',
            'strand' => 'ICT',
            'track' => 'CSS NCII',
            'schoolYear' => $enrollment->schoolYear,
            'trainingLevel' => null,
            'assignedTeacherId' => null,
            'assignedSection' => null,
            'reviewNotes' => null,
            'rejectionReason' => null,
            'reviewedBy' => null,
        ]];
    }
}
