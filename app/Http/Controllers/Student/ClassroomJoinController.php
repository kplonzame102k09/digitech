<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassroomJoinController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $classrooms = Classroom::query()
            ->join('classroom_student', 'classroom_student.classroom_id', '=', 'classrooms.id')
            ->where('classroom_student.student_id', $user->id)
            ->with('teacher')
            ->select('classrooms.*')
            ->orderByDesc('classrooms.updated_at')
            ->get();

        return response()->json([
            'ok' => true,
            'classrooms' => $classrooms->map(fn (Classroom $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subject' => $c->subject,
                'description' => $c->description,
                'status' => $c->status,
                'teacherName' => $c->teacher ? trim($c->teacher->firstName.' '.$c->teacher->lastName) : null,
                'teacherId' => $c->teacherId,
            ])->values(),
        ]);
    }

    public function preview(Request $request, string $token): JsonResponse
    {
        $classroom = Classroom::query()
            ->where('inviteToken', $token)
            ->where('status', Classroom::ACTIVE)
            ->with('teacher')
            ->first();

        if (! $classroom) {
            return response()->json(['ok' => false, 'error' => 'This invite link is invalid or expired.'], 404);
        }

        $user = $request->user();
        $joined = $user->isStudent()
            ? $classroom->students()->where('users.id', $user->id)->exists()
            : false;

        return response()->json([
            'ok' => true,
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'subject' => $classroom->subject,
                'description' => $classroom->description,
                'teacherName' => $classroom->teacher ? trim($classroom->teacher->firstName.' '.$classroom->teacher->lastName) : 'Your teacher',
                'joined' => $joined,
            ],
        ]);
    }

    /**
     * Join a classroom via 9-digit code or invite token.
     * Idempotent: re-joining returns ok + alreadyJoined.
     * Also assigns the student's enrollment teacher/section.
     */
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403, 'Only students can join classrooms.');

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        abort_if(empty($validated['code']) && empty($validated['token']), 422, 'Provide a class code or invite link.');

        $classroom = null;

        if (! empty($validated['token'])) {
            $classroom = Classroom::query()
                ->where('inviteToken', trim($validated['token']))
                ->where('status', Classroom::ACTIVE)
                ->first();
        } else {
            $digits = Classroom::normalizeCode($validated['code']);
            abort_unless(strlen($digits) === 9, 422, 'Class codes are 9 digits.');

            $classroom = Classroom::query()
                ->where('inviteCode', $digits)
                ->where('status', Classroom::ACTIVE)
                ->first();
        }

        if (! $classroom) {
            return response()->json(['ok' => false, 'error' => 'This code or link is invalid or expired.'], 422);
        }

        $already = $classroom->students()->where('users.id', $user->id)->exists();

        DB::transaction(function () use ($classroom, $user): void {
            $classroom->students()->syncWithoutDetaching([
                $user->id => ['joined_at' => now()],
            ]);

            // Assign enrollment teacher + section (pivot is the roster source
            // of truth; enrollment assignment keeps roster/grades/attendance
            // scoping working through getEnrolledStudentIds).
            $enrollment = Enrollment::query()
                ->where('studentId', $user->user_id)
                ->orderByDesc('updated_at')
                ->first();

            if ($enrollment) {
                $enrollment->assignedTeacherId = $classroom->teacherId;
                $enrollment->assignedSection = $classroom->name;
                $enrollment->save();
            }
        });

        if (! $already) {
            AuditLog::record([
                'entity' => 'classroom',
                'recordId' => $classroom->id,
                'action' => 'classroom.joined',
                'actorId' => $user->user_id,
                'notes' => "Student {$user->user_id} joined {$classroom->name}",
            ]);

            if ((bool) (SystemSetting::getInstance()->notifyTeachers ?? true)) {
                $studentName = trim($user->firstName.' '.$user->lastName) ?: $user->user_id;
                Notification::alert(
                    (string) $classroom->teacherId,
                    'New student joined',
                    "{$studentName} joined {$classroom->name}.",
                    'enrollment',
                    (string) $classroom->id,
                );
            }
        }

        return response()->json([
            'ok' => true,
            'alreadyJoined' => $already,
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'subject' => $classroom->subject,
                'teacherId' => $classroom->teacherId,
            ],
        ]);
    }
}
