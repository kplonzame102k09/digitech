<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'teacher',
            'department' => 'Senior High School',
        ], $overrides));
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

    public function test_filters_and_classrooms_reflect_the_teachers_own_classrooms(): void
    {
        $teacher = $this->teacher();
        $this->classroom($teacher, ['name' => 'STEM - A', 'subject' => 'GENERAL MATHEMATICS', 'section' => 'STEM - A']);
        $this->classroom($teacher, ['name' => 'STEM - B', 'subject' => 'CALCULUS 101', 'section' => 'STEM - B']);

        $response = $this->actingAs($teacher)->getJson('/teacher/api/attendance/filters');
        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('academicYears.0', '2026-2027')
            ->assertJsonPath('sections.0', 'STEM - A')
            ->assertJsonPath('sections.1', 'STEM - B')
            ->assertJsonPath('subjects.0', 'CALCULUS 101')
            ->assertJsonPath('subjects.1', 'GENERAL MATHEMATICS');

        $this->actingAs($teacher)
            ->getJson('/teacher/api/attendance/classrooms?subject=CALCULUS 101')
            ->assertOk()
            ->assertJsonCount(1, 'classrooms')
            ->assertJsonPath('classrooms.0.subject', 'CALCULUS 101');

        $other = $this->teacher();
        $this->actingAs($other)
            ->getJson('/teacher/api/attendance/filters')
            ->assertOk()
            ->assertJsonCount(0, 'academicYears');
    }

    public function test_teacher_can_start_a_session_and_mark_a_normal_student(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $studentA = $this->student();
        $studentB = $this->student();
        $classroom->students()->attach([$studentA->id, $studentB->id]);

        $start = $this->start($classroom, $teacher);
        $start->assertOk()->assertJsonPath('session.status', 'open');
        $sessionId = $start->json('session.id');

        $mark = $this->actingAs($teacher)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $studentA->user_id,
                'status' => 'Present',
            ]);
        $mark->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('attendance', [
            'studentId' => $studentA->user_id,
            'sessionId' => $sessionId,
            'kind' => Attendance::KIND_CHECK,
            'status' => 'Present',
        ]);
    }

    public function test_open_session_blocks_a_second_start_for_the_same_day(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);

        $this->start($classroom, $teacher)->assertOk();

        $this->start($classroom, $teacher)
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_conflicted_student_must_be_verified_by_the_adviser_before_marking(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher, ['name' => 'STEM - ALGEBRA', 'section' => 'STEM - ALGEBRA']);
        $art = $this->classroom($teacher, [
            'name' => 'STEM - ART',
            'subject' => 'ART APPRECIATION',
            'section' => 'STEM - ART',
            'startTime' => '08:30:00',
            'endTime' => '09:30:00',
        ]);

        $student = $this->student();
        $classroom->students()->attach($student->id);
        $art->students()->attach($student->id);
        $this->enrollAdviser($student, $teacher);

        $sessionId = $this->start($classroom, $teacher)->json('session.id');

        $this->actingAs($teacher)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $student->user_id,
                'status' => 'Late',
            ])
            ->assertStatus(422)
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('student.requiresVerification', true);

        $this->actingAs($teacher)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/verify", [
                'studentId' => $student->user_id,
                'confirmed' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('attendance', [
            'studentId' => $student->user_id,
            'sessionId' => $sessionId,
            'verificationStatus' => Attendance::VERIFICATION_CONFIRMED,
        ]);

        $this->actingAs($teacher)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $student->user_id,
                'status' => 'Late',
            ])
            ->assertOk()
            ->assertJsonPath('student.status', 'Late');
    }

    public function test_non_adviser_cannot_verify_a_conflicted_student(): void
    {
        $teacher = $this->teacher();
        $other = $this->teacher();
        $classroom = $this->classroom($teacher);
        $art = $this->classroom($teacher, ['name' => 'STEM - ART', 'subject' => 'ART', 'section' => 'STEM - ART', 'startTime' => '08:30:00', 'endTime' => '09:30:00']);

        $student = $this->student();
        $classroom->students()->attach($student->id);
        $art->students()->attach($student->id);
        $this->enrollAdviser($student, $teacher);

        $sessionId = $this->start($classroom, $teacher)->json('session.id');

        $this->actingAs($other)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/verify", [
                'studentId' => $student->user_id,
                'confirmed' => true,
            ])
            ->assertStatus(403);
    }

    public function test_submit_locks_the_session_without_writing_finals(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $studentA = $this->student();
        $studentB = $this->student();
        $classroom->students()->attach([$studentA->id, $studentB->id]);

        $sessionId = $this->start($classroom, $teacher)->json('session.id');

        $this->actingAs($teacher)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $studentA->user_id,
                'status' => 'Present',
            ])
            ->assertOk();

        $this->actingAs($teacher)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/submit")
            ->assertOk()
            ->assertJsonPath('locked', true);

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $sessionId,
            'status' => AttendanceSession::LOCKED,
        ]);

        // Finals are deferred to the advisory review + admin finalization
        // flow: a locked session must NOT leave any official final rows.
        foreach ([$studentA, $studentB] as $student) {
            $this->assertDatabaseMissing('attendance', [
                'studentId' => $student->user_id,
                'date' => '2026-09-16',
                'classroomId' => '',
                'kind' => Attendance::KIND_FINAL,
            ]);
        }

        $this->assertDatabaseHas('audit_logs', [
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'recordId' => $sessionId,
            'action' => 'attendance.session_submitted',
        ]);

        $this->actingAs($teacher)
            ->postJson("/teacher/api/attendance/sessions/{$sessionId}/mark", [
                'studentId' => $studentA->user_id,
                'status' => 'Excused',
            ])
            ->assertStatus(422)
            ->assertJsonPath('blocked', true);
    }

    public function test_an_ended_session_auto_submits_when_next_loaded(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $student = $this->student();
        $classroom->students()->attach($student->id);

        $sessionId = $this->start($classroom, $teacher, [
            'scheduledEndTime' => '00:00',
            'autoSubmit' => true,
        ])->json('session.id');

        $this->actingAs($teacher)
            ->getJson("/teacher/api/attendance/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('locked', true);

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $sessionId,
            'status' => AttendanceSession::LOCKED,
        ]);

        $this->assertDatabaseMissing('attendance', [
            'studentId' => $student->user_id,
            'date' => '2026-09-16',
            'kind' => Attendance::KIND_FINAL,
        ]);
    }

    public function test_teacher_cannot_manage_another_teachers_classroom(): void
    {
        $owner = $this->teacher();
        $intruder = $this->teacher();
        $classroom = $this->classroom($owner);

        $this->start($classroom, $intruder)->assertStatus(403);
    }
}
