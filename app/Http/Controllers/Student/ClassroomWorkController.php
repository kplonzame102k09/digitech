<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\ClassroomActivity;
use App\Models\ClassroomSubmission;
use App\Policies\ClassroomWorkPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomWorkController extends Controller
{
    public function __construct(private ClassroomWorkPolicy $work) {}

    public function activities(Request $request, string $classroomId): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($classroomId);
        abort_unless($this->work->canViewClassroom($user, $classroom), 403);

        $activities = ClassroomActivity::query()
            ->where('classroom_id', $classroom->id)
            ->orderByDesc('created_at')
            ->get();

        $mine = [];
        if ($user->isStudent()) {
            $mine = ClassroomSubmission::query()
                ->whereIn('activity_id', $activities->pluck('id')->all() === [] ? ['__none__'] : $activities->pluck('id')->all())
                ->where('studentId', $user->user_id)
                ->get()
                ->keyBy('activity_id');
        }

        // Students see per-activity scores only; per-term grades (averages,
        // final, GWA) are teacher-side in the gradebook.
        return response()->json([
            'ok' => true,
            'subject' => $classroom->subject,
            'activities' => $activities->map(fn (ClassroomActivity $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'description' => $a->description,
                'term' => $a->term ?? 'Prelim',
                'dueDate' => $a->dueDate?->format('Y-m-d'),
                'submission' => isset($mine[$a->id]) ? [
                    'id' => $mine[$a->id]->id,
                    'status' => $mine[$a->id]->status,
                    'score' => $mine[$a->id]->score !== null ? (float) $mine[$a->id]->score : null,
                    'fileUrl' => $mine[$a->id]->fileUrl,
                    'submittedAt' => $mine[$a->id]->submittedAt?->toIso8601String(),
                ] : null,
            ])->values(),
        ]);
    }

    /**
     * Submit (or resubmit) an activity file. One row per student per activity.
     */
    public function submit(Request $request, string $activityId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403, 'Only students can submit.');

        $activity = ClassroomActivity::query()->findOrFail($activityId);
        $classroom = $activity->classroom;
        abort_unless($classroom && $classroom->isActive(), 422, 'This classroom is archived.');
        abort_unless($this->work->isRosterStudent($user, $classroom), 403, 'Join the classroom first.');

        $validated = $request->validate([
            'fileUrl' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $fileUrl = self::normalizeClassroomFileUrl($validated['fileUrl']);
        abort_unless($fileUrl !== null, 422, 'Invalid file reference.');

        $submission = ClassroomSubmission::query()
            ->where('activity_id', $activity->id)
            ->where('studentId', $user->user_id)
            ->first();

        if ($submission) {
            $submission->fileUrl = $fileUrl;
            $submission->notes = $validated['notes'] ?? $submission->notes;
            $submission->status = ClassroomSubmission::SUBMITTED;
            $submission->submittedAt = now();
            $submission->score = null;
            $submission->gradedAt = null;
            $submission->gradedBy = null;
            $submission->save();
        } else {
            $submission = ClassroomSubmission::create([
                'activity_id' => $activity->id,
                'studentId' => $user->user_id,
                'fileUrl' => $fileUrl,
                'notes' => $validated['notes'] ?? null,
                'status' => ClassroomSubmission::SUBMITTED,
                'submittedAt' => now(),
            ]);
        }

        AuditLog::record([
            'entity' => AuditLog::ENTITY_SUBMISSION,
            'recordId' => (string) $submission->id,
            'action' => 'submission.submitted',
            'notes' => "Submitted {$activity->title} in {$classroom->name}.",
            'actorId' => $user->user_id,
        ]);

        return response()->json(['ok' => true, 'submission' => [
            'id' => $submission->id,
            'status' => $submission->status,
        ]], 201);
    }

    private function isClassroomFileUrl(string $url): bool
    {
        return self::normalizeClassroomFileUrl($url) !== null;
    }

    /**
     * Normalize any classroom-file reference the upload endpoint (or a
     * pasted link) can produce into the canonical relative form
     * `/api/classroom-files/{basename}`. Absolute app URLs from
     * `url('/api/classroom-files/...')` are the common case.
     */
    public static function normalizeClassroomFileUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#/api/classroom-files/([A-Za-z0-9_.-]+)(?:\?.*)?$#', $url, $m)) {
            return '/api/classroom-files/'.rawurlencode($m[1]);
        }

        if (preg_match('#^classroom-files/([A-Za-z0-9_.-]+)$#', $url, $m)) {
            return '/api/classroom-files/'.rawurlencode($m[1]);
        }

        if (preg_match('#(?:^|/)storage/classroom-files/([A-Za-z0-9_.-]+)(?:\?.*)?$#', $url, $m)) {
            return '/api/classroom-files/'.rawurlencode($m[1]);
        }

        return null;
    }
}
