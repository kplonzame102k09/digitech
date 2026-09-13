<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Policies\ClassroomWorkPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomDetailController extends Controller
{
    public function __construct(private ClassroomWorkPolicy $work) {}

    /**
     * Student-safe classroom detail: meta + classmates.
     * Roster-member only; inviteCode/inviteToken/joinLink never exposed.
     * Activities/submissions come from ClassroomWorkController@activities.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403);

        $classroom = Classroom::query()->findOrFail($id);
        abort_unless($this->work->isRosterStudent($user, $classroom), 403);
        abort_unless($classroom->isActive(), 422, 'This classroom is archived.');

        $classroom->load(['teacher', 'students' => fn ($q) => $q->orderBy('firstName')->orderBy('lastName')]);

        return response()->json([
            'ok' => true,
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'subject' => $classroom->subject,
                'description' => $classroom->description,
                'status' => $classroom->status,
                'teacherId' => $classroom->teacherId,
                'teacherName' => $classroom->teacher
                    ? trim($classroom->teacher->firstName.' '.$classroom->teacher->lastName)
                    : null,
                'studentsCount' => $classroom->students->count(),
            ],
            'classmates' => $classroom->students->map(fn ($s) => [
                'id' => $s->user_id,
                'firstName' => $s->firstName,
                'lastName' => $s->lastName,
                'email' => $s->email,
                'photo' => $s->photo,
                'joinedAt' => $s->pivot?->joined_at,
            ])->values(),
        ]);
    }
}
