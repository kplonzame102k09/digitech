<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\ClassroomActivity;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Policies\ClassroomWorkPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomActivityController extends Controller
{
    public function __construct(private ClassroomWorkPolicy $work) {}

    private function classroomFor(User $user, string $id): Classroom
    {
        $classroom = Classroom::query()->findOrFail($id);
        abort_unless($this->work->ownsClassroom($user, $classroom), 403);

        return $classroom;
    }

    public function index(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        abort_unless(
            $this->work->ownsClassroom($request->user(), $classroom)
            || $request->user()->isAdmin()
            || $this->work->isRosterStudent($request->user(), $classroom),
            403
        );

        $activities = ClassroomActivity::query()
            ->where('classroom_id', $classroom->id)
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ok' => true,
            'subject' => $classroom->subject,
            'activities' => $activities->map(fn (ClassroomActivity $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'description' => $a->description,
                'term' => $a->term ?? 'Prelim',
                'dueDate' => $a->dueDate?->format('Y-m-d'),
                'submissionsCount' => $a->submissions_count,
                'createdAt' => $a->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $classroom = $this->classroomFor($request->user(), $id);

        // No subject accepted here: activities inherit the fixed classroom subject.
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'term' => ['nullable', 'string', 'max:20'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $activity = ClassroomActivity::create([
            'classroom_id' => $classroom->id,
            'title' => trim($validated['title']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'term' => \App\Services\ClassroomGradeService::normalizeTerm($validated['term'] ?? null),
            'dueDate' => $validated['dueDate'] ?? null,
            'createdBy' => $request->user()->user_id,
        ]);

        AuditLog::record([
            'entity' => 'classroom',
            'recordId' => $classroom->id,
            'action' => 'classroom.activity_created',
            'actorId' => $request->user()->user_id,
            'notes' => "Activity {$activity->title} created",
        ]);

        if ((bool) (SystemSetting::getInstance()->notifyStudents ?? true)) {
            $rosterIds = $classroom->students()->pluck('users.user_id')->all();
            foreach ($rosterIds as $studentUserId) {
                Notification::alert(
                    (string) $studentUserId,
                    'New activity posted',
                    "\"{$activity->title}\" was posted in {$classroom->name}.",
                    'activity',
                    (string) $activity->id,
                );
            }
        }

        return response()->json(['ok' => true, 'activity' => [
            'id' => $activity->id,
            'title' => $activity->title,
            'description' => $activity->description,
            'term' => $activity->term,
            'dueDate' => $activity->dueDate?->format('Y-m-d'),
        ]], 201);
    }

    public function destroy(Request $request, string $id, string $activityId): JsonResponse
    {
        $classroom = $this->classroomFor($request->user(), $id);

        $activity = ClassroomActivity::query()
            ->where('id', $activityId)
            ->where('classroom_id', $classroom->id)
            ->firstOrFail();
        $activity->delete();

        AuditLog::record([
            'entity' => AuditLog::ENTITY_ACTIVITY,
            'recordId' => (string) $activityId,
            'action' => 'activity.deleted',
            'notes' => "Activity {$activity->title} deleted.",
            'actorId' => $request->user()->user_id,
        ]);

        return response()->json(['ok' => true]);
    }
}
