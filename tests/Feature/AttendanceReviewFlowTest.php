<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendancePackage;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AttendanceAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'teacher',
            'department' => 'Senior High School',
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function classroom(User $teacher, array $overrides = []): Classroom
    {
        return Classroom::create(array_merge([
            'teacherId' => $teacher->user_id,
            'name' => 'STEM - TEST SECTION',
            'subject' => 'GENERAL MATHEMATICS',
            'academicYear' => '2026-2027',
            'department' => 'Senior High School',
            'section' => 'STEM - TEST SECTION',
            'startTime' => '08:00:00',
            'endTime' => '09:00:00',
            'status' => Classroom::ACTIVE,
        ], $overrides));
    }

    private function enrollAdviser(User $student, User $adviser): void
    {
        Enrollment::factory()->create([
            'studentId' => $student->user_id,
            'assignedTeacherId' => $adviser->user_id,
            'status' => 'Enrolled',
        ]);
    }

    private function start(Classroom $classroom, User $teacher, array $overrides = []): TestResponse
    {
        return $this->actingAs($teacher)->postJson('/teacher/api/attendance/sessions', array_merge([
            'classroomId' => $classroom->id,
            'date' => '2026-09-16',
            'autoSubmit' => false,
        ], $overrides));
    }

    /** @return array{adviser:User,classTeacher:User,classroom:Classroom,studentA:User,studentB:User} */
    private function reviewFixture(): array
    {
        $adviser = $this->teacher();
        $classTeacher = $this->teacher();
        $classroom = $this->classroom($classTeacher);
        // Deterministic names so the service's alphabetical (firstName, lastName)
        // ordering is stable across assertions.
        $studentA = User::factory()->create(['role' => 'student', 'firstName' => 'Aaron', 'lastName' => 'Alpha']);
        $studentB = User::factory()->create(['role' => 'student', 'firstName' => 'Beth', 'lastName' => 'Beta']);
        $classroom->students()->attach([$studentA->id, $studentB->id]);
        $this->enrollAdviser($studentA, $adviser);
        $this->enrollAdviser($studentB, $adviser);

        return compact('adviser', 'classTeacher', 'classroom', 'studentA', 'studentB');
    }

    /** Record one check per student in a locked session, owned by $classTeacher. */
    private function markDay(array $fixture): void
    {
        $sessionId = $this->start($fixture['classroom'], $fixture['classTeacher'])->json('session.id');

        $this->actingAs($fixture['classTeacher'])
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $fixture['studentA']->user_id,
                'status' => 'Present',
            ])
            ->assertOk();

        $this->actingAs($fixture['classTeacher'])
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $fixture['studentB']->user_id,
                'status' => 'Absent',
            ])
            ->assertOk();

        $this->actingAs($fixture['classTeacher'])
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/submit")
            ->assertOk()
            ->assertJsonPath('locked', true);
    }

    public function test_adviser_sees_advisees_with_their_classroom_marks(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);

        $this->actingAs($fixture['adviser'])
            ->getJson('/teacher/api/attendance/review?date=2026-09-16')
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('adviser.id', $fixture['adviser']->user_id)
            ->assertJsonCount(2, 'students')
            ->assertJsonPath('students.0.id', $fixture['studentA']->user_id)
            ->assertJsonPath('students.0.checks.0.status', 'Present')
            ->assertJsonPath('students.0.editable', true)
            ->assertJsonPath('package', null);
    }

    public function test_adviser_edits_a_whole_day_final_for_an_advisee(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);

        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review', [
                'date' => '2026-09-16',
                'studentId' => $fixture['studentA']->user_id,
                'status' => 'Excused',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('attendance', [
            'studentId' => $fixture['studentA']->user_id,
            'date' => '2026-09-16',
            'classroomId' => '',
            'session' => 1,
            'kind' => Attendance::KIND_FINAL,
            'status' => 'Excused',
            'recordedBy' => $fixture['adviser']->user_id,
            'isLocked' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'action' => 'attendance.review_edited',
            'actorId' => $fixture['adviser']->user_id,
        ]);
    }

    public function test_a_teacher_cannot_edit_status_for_students_they_do_not_advise(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);

        $stranger = $this->teacher();

        $this->actingAs($stranger)
            ->postJson('/teacher/api/attendance/review', [
                'date' => '2026-09-16',
                'studentId' => $fixture['studentA']->user_id,
                'status' => 'Present',
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('attendance', [
            'studentId' => $fixture['studentA']->user_id,
            'kind' => Attendance::KIND_FINAL,
        ]);
    }

    public function test_adviser_submits_the_day_writing_finals_and_a_package(): void
    {
        $fixture = $this->reviewFixture();
        // Only studentA has a classroom mark; studentB stays unmarked.
        $sessionId = $this->start($fixture['classroom'], $fixture['classTeacher'])->json('session.id');
        $this->actingAs($fixture['classTeacher'])
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $fixture['studentA']->user_id,
                'status' => 'Present',
            ])
            ->assertOk();
        $this->actingAs($fixture['classTeacher'])
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/submit")
            ->assertOk();

        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review/submit', ['date' => '2026-09-16'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('package.status', AttendancePackage::SUBMITTED);

        // Present-like check -> Present; unmarked student -> Absent.
        $this->assertDatabaseHas('attendance', [
            'studentId' => $fixture['studentA']->user_id,
            'date' => '2026-09-16',
            'classroomId' => '',
            'kind' => Attendance::KIND_FINAL,
            'status' => 'Present',
            'isLocked' => false,
            'finalizedAt' => null,
        ]);
        $this->assertDatabaseHas('attendance', [
            'studentId' => $fixture['studentB']->user_id,
            'date' => '2026-09-16',
            'classroomId' => '',
            'kind' => Attendance::KIND_FINAL,
            'status' => 'Absent',
        ]);

        $this->assertDatabaseHas('attendance_packages', [
            'adviserId' => $fixture['adviser']->user_id,
            'date' => '2026-09-16',
            'status' => AttendancePackage::SUBMITTED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'action' => 'attendance.package_submitted',
            'actorId' => $fixture['adviser']->user_id,
        ]);

        // The workspace is now read-only.
        $this->actingAs($fixture['adviser'])
            ->getJson('/teacher/api/attendance/review?date=2026-09-16')
            ->assertOk()
            ->assertJsonPath('package.status', AttendancePackage::SUBMITTED)
            ->assertJsonPath('students.0.editable', false);
    }

    public function test_edits_are_blocked_after_submitting(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);

        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review/submit', ['date' => '2026-09-16'])
            ->assertOk();

        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review', [
                'date' => '2026-09-16',
                'studentId' => $fixture['studentA']->user_id,
                'status' => 'Absent',
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('attendance', [
            'studentId' => $fixture['studentA']->user_id,
            'date' => '2026-09-16',
            'kind' => Attendance::KIND_FINAL,
            'status' => 'Present',
        ]);
    }

    public function test_admin_returns_a_package_adviser_reedits_and_resubmits(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);
        $admin = $this->admin();

        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review/submit', ['date' => '2026-09-16'])
            ->assertOk();

        $packageId = AttendancePackage::query()->value('id');

        // Admin returns it to the adviser for correction.
        $this->actingAs($admin)
            ->postJson("/admin/api/attendance/finalize/{$packageId}/return", ['reason' => 'Verify the late excuse for Babson'])
            ->assertOk()
            ->assertJsonPath('package.status', AttendancePackage::RETURNED);

        $this->assertDatabaseHas('attendance_packages', [
            'id' => $packageId,
            'status' => AttendancePackage::RETURNED,
            'returnedBy' => $admin->user_id,
            'returnReason' => 'Verify the late excuse for Babson',
        ]);

        // The adviser workspace becomes editable again.
        $this->actingAs($fixture['adviser'])
            ->getJson('/teacher/api/attendance/review?date=2026-09-16')
            ->assertOk()
            ->assertJsonPath('package.status', AttendancePackage::RETURNED)
            ->assertJsonPath('students.0.editable', true);

        // The adviser corrects studentB (Absent -> Excused).
        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review', [
                'date' => '2026-09-16',
                'studentId' => $fixture['studentB']->user_id,
                'status' => 'Excused',
            ])
            ->assertOk();

        $this->assertDatabaseHas('attendance', [
            'studentId' => $fixture['studentB']->user_id,
            'date' => '2026-09-16',
            'kind' => Attendance::KIND_FINAL,
            'status' => 'Excused',
            'isLocked' => false,
        ]);

        // Resubmitting clears the return and goes back to submitted.
        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review/submit', ['date' => '2026-09-16'])
            ->assertOk()
            ->assertJsonPath('package.status', AttendancePackage::SUBMITTED);

        $this->assertDatabaseHas('attendance_packages', [
            'id' => $packageId,
            'status' => AttendancePackage::SUBMITTED,
            'returnedAt' => null,
            'returnReason' => null,
        ]);

        // The admin finalizes the corrected resubmission.
        $this->actingAs($admin)
            ->postJson("/admin/api/attendance/finalize/{$packageId}")
            ->assertOk()
            ->assertJsonPath('package.status', AttendancePackage::FINALIZED);

        $this->assertDatabaseHas('attendance', [
            'studentId' => $fixture['studentB']->user_id,
            'date' => '2026-09-16',
            'kind' => Attendance::KIND_FINAL,
            'status' => 'Excused',
            'isLocked' => true,
            'finalizedBy' => $admin->user_id,
        ]);
    }

    public function test_admin_lists_and_finalizes_a_package(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);
        $admin = $this->admin();

        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review/submit', ['date' => '2026-09-16'])
            ->assertOk();

        $packageId = AttendancePackage::query()->value('id');

        $this->actingAs($admin)
            ->getJson('/admin/api/attendance/finalize')
            ->assertOk()
            ->assertJsonCount(1, 'packages')
            ->assertJsonPath('packages.0.id', $packageId)
            ->assertJsonPath('packages.0.status', AttendancePackage::SUBMITTED)
            ->assertJsonPath('packages.0.summary.students', 2);

        $this->actingAs($admin)
            ->getJson("/admin/api/attendance/finalize/{$packageId}")
            ->assertOk()
            ->assertJsonCount(2, 'students')
            ->assertJsonPath('students.0.final.status', 'Present');

        $this->actingAs($admin)
            ->postJson("/admin/api/attendance/finalize/{$packageId}")
            ->assertOk()
            ->assertJsonPath('package.status', AttendancePackage::FINALIZED);

        foreach ([$fixture['studentA'], $fixture['studentB']] as $student) {
            $this->assertDatabaseHas('attendance', [
                'studentId' => $student->user_id,
                'date' => '2026-09-16',
                'kind' => Attendance::KIND_FINAL,
                'isLocked' => true,
                'finalizedBy' => $admin->user_id,
            ]);
        }

        $this->assertDatabaseHas('attendance_packages', [
            'id' => $packageId,
            'status' => AttendancePackage::FINALIZED,
            'finalizedBy' => $admin->user_id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'action' => 'attendance.package_finalized',
            'actorId' => $admin->user_id,
        ]);
    }

    public function test_finalizing_an_already_finalized_package_is_rejected(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);
        $admin = $this->admin();

        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review/submit', ['date' => '2026-09-16'])
            ->assertOk();

        $packageId = AttendancePackage::query()->value('id');

        $this->actingAs($admin)->postJson("/admin/api/attendance/finalize/{$packageId}")->assertOk();

        $this->actingAs($admin)
            ->postJson("/admin/api/attendance/finalize/{$packageId}")
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_analytics_only_counts_admin_finalized_finals(): void
    {
        $fixture = $this->reviewFixture();
        $this->markDay($fixture);
        $admin = $this->admin();
        $service = app(AttendanceAnalyticsService::class);

        // Pending finals (submitted, not finalized) must not count.
        $this->actingAs($fixture['adviser'])
            ->postJson('/teacher/api/attendance/review/submit', ['date' => '2026-09-16'])
            ->assertOk();

        $overall = $service->overall($admin);
        $this->assertSame(0, $overall['total']);
        $this->assertSame(0.0, $overall['rate']);

        $packageId = AttendancePackage::query()->value('id');
        $this->actingAs($admin)->postJson("/admin/api/attendance/finalize/{$packageId}")->assertOk();

        $finalized = $service->overall($admin);
        $this->assertSame(2, $finalized['total']);
        $this->assertSame(1, $finalized['present']);
        $this->assertSame(1, $finalized['absent']);
        $this->assertSame(50.0, $finalized['rate']);
    }
}
