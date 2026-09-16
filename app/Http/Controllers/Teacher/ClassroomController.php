<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassroomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', Classroom::class);

        $query = Classroom::query()->orderByDesc('updated_at');

        if (! $user->isAdmin()) {
            $query->where('teacherId', $user->user_id);
        }

        $classrooms = $query->withCount('students')->get();

        return response()->json([
            'ok' => true,
            'classrooms' => $classrooms->map(fn (Classroom $c) => $this->serialize($c))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('create', Classroom::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9 .,\'\/\-]{0,49}$/'],
            // Subject is fixed per classroom: required at create, immutable after.
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sessionsPerDay' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $classroom = Classroom::create([
            'teacherId' => $user->user_id,
            'name' => trim($validated['name']),
            'subject' => trim($validated['subject']),
            'sessionsPerDay' => (int) ($validated['sessionsPerDay'] ?? 1),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
        ]);

        AuditLog::record([
            'entity' => 'classroom',
            'recordId' => $classroom->id,
            'action' => 'classroom.created',
            'actorId' => $user->user_id,
            'notes' => "Classroom {$classroom->name} created",
        ]);

        return response()->json(['ok' => true, 'classroom' => $this->serialize($classroom)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $this->authorize('view', $classroom);

        $classroom->load(['students' => fn ($q) => $q->orderBy('firstName')->orderBy('lastName')]);

        $data = $this->serialize($classroom);
        $data['students'] = $classroom->students->map(fn (User $s) => [
            'id' => $s->user_id,
            'firstName' => $s->firstName,
            'lastName' => $s->lastName,
            'email' => $s->email,
            'photo' => $s->photo,
            'joinedAt' => $s->pivot?->joined_at,
        ])->values();

        return response()->json(['ok' => true, 'classroom' => $data]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $this->authorize('update', $classroom);

        // Subject is fixed per classroom: reject any attempt to change it so
        // existing grade histories (gradeKey = student|subject|year|semester)
        // never split or orphan.
        if ($request->has('subject') && trim((string) $request->input('subject')) !== (string) $classroom->subject) {
            return response()->json(['ok' => false, 'error' => 'Subject is fixed per classroom and cannot be changed.'], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9 .,\'\/\-]{0,49}$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sessionsPerDay' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        if (array_key_exists('name', $validated)) {
            $classroom->name = trim($validated['name']);
        }
        if (array_key_exists('description', $validated)) {
            $classroom->description = $validated['description'] !== null ? trim($validated['description']) : null;
        }
        if (array_key_exists('sessionsPerDay', $validated)) {
            $classroom->sessionsPerDay = (int) $validated['sessionsPerDay'];
        }
        $classroom->save();

        return response()->json(['ok' => true, 'classroom' => $this->serialize($classroom)]);
    }

    public function regenerate(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $this->authorize('manage', $classroom);

        $validated = $request->validate([
            'target' => ['nullable', 'string', 'in:code,token,both'],
        ]);

        $target = $validated['target'] ?? 'both';

        if ($target === 'code' || $target === 'both') {
            $classroom->inviteCode = Classroom::generateInviteCode();
        }
        if ($target === 'token' || $target === 'both') {
            $classroom->inviteToken = Str::random(32);
        }
        $classroom->save();

        AuditLog::record([
            'entity' => 'classroom',
            'recordId' => $classroom->id,
            'action' => 'classroom.invite_regenerated',
            'actorId' => $request->user()->user_id,
            'notes' => "Invite {$target} regenerated",
        ]);

        return response()->json(['ok' => true, 'classroom' => $this->serialize($classroom)]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $this->authorize('manage', $classroom);

        $classroom->status = $classroom->isActive() ? Classroom::ARCHIVED : Classroom::ACTIVE;
        $classroom->save();

        AuditLog::record([
            'entity' => AuditLog::ENTITY_CLASSROOM,
            'recordId' => (string) $classroom->id,
            'action' => $classroom->isActive() ? 'classroom.reopened' : 'classroom.archived',
            'notes' => "Classroom {$classroom->name} ".($classroom->isActive() ? 'reopened' : 'archived').'.',
            'actorId' => $request->user()->user_id,
        ]);

        return response()->json(['ok' => true, 'classroom' => $this->serialize($classroom)]);
    }

    public function removeStudent(Request $request, string $id, string $studentId): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $this->authorize('manage', $classroom);

        $student = User::query()->where('user_id', $studentId)->where('role', 'student')->firstOrFail();
        $classroom->students()->detach($student->id);

        AuditLog::record([
            'entity' => AuditLog::ENTITY_CLASSROOM,
            'recordId' => (string) $classroom->id,
            'action' => 'classroom.student_removed',
            'notes' => "Student {$studentId} removed from {$classroom->name}.",
            'actorId' => $request->user()->user_id,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Permanently delete a classroom. Child rows (roster, activities,
     * submissions, meetings, attendance sessions) cascade via FK constraints;
     * provisional attendance checks are removed explicitly while admin-
     * finalized finals are kept as the official record.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $this->authorize('delete', $classroom);

        Attendance::query()
            ->where('classroomId', $id)
            ->where('kind', Attendance::KIND_CHECK)
            ->delete();

        $classroom->delete();

        AuditLog::record([
            'entity' => AuditLog::ENTITY_CLASSROOM,
            'recordId' => (string) $classroom->id,
            'action' => 'classroom.deleted',
            'notes' => "Classroom {$classroom->name} deleted.",
            'actorId' => $request->user()->user_id,
        ]);

        return response()->json(['ok' => true, 'deletedId' => $classroom->id]);
    }

    private function serialize(Classroom $classroom): array
    {
        return [
            'id' => $classroom->id,
            'teacherId' => $classroom->teacherId,
            'name' => $classroom->name,
            'subject' => $classroom->subject,
            'sessionsPerDay' => (int) ($classroom->sessionsPerDay ?? 1),
            'description' => $classroom->description,
            'inviteCode' => $classroom->inviteCode,
            'inviteCodeFormatted' => Classroom::formatCode($classroom->inviteCode),
            'joinLink' => $classroom->joinLink(),
            'status' => $classroom->status,
            'studentsCount' => $classroom->students_count ?? $classroom->students()->count(),
            'createdAt' => $classroom->created_at?->toIso8601String(),
            'updatedAt' => $classroom->updated_at?->toIso8601String(),
        ];
    }
}
