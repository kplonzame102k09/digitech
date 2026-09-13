<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomMeeting;
use App\Policies\ClassroomWorkPolicy;
use App\Services\VideoTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomMeetingController extends Controller
{
    public function __construct(
        private ClassroomWorkPolicy $work,
        private VideoTokenService $video,
    ) {}

    /**
     * Upcoming + live sessions only (no cancelled/ended).
     */
    public function index(Request $request, string $classroomId): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($classroomId);
        abort_unless($this->work->isRosterStudent($user, $classroom), 403);

        $meetings = ClassroomMeeting::query()
            ->where('classroom_id', $classroom->id)
            ->whereIn('status', [ClassroomMeeting::SCHEDULED, ClassroomMeeting::LIVE])
            ->orderBy('starts_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ok' => true,
            'videoEnabled' => $this->video->isEnabled(),
            'meetings' => $meetings->map(fn (ClassroomMeeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'starts_at' => $m->starts_at?->toIso8601String(),
                'ends_at' => $m->ends_at?->toIso8601String(),
                'status' => $m->status,
                'joinable' => $m->isJoinable(),
            ])->values(),
        ]);
    }

    /**
     * Mint a join token. meetingId optional: instant room when omitted.
     */
    public function token(Request $request, string $classroomId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403);

        $classroom = Classroom::query()->findOrFail($classroomId);
        abort_unless($classroom->isActive(), 422, 'This classroom is archived.');
        abort_unless($this->work->isRosterStudent($user, $classroom), 403);

        $validated = $request->validate([
            'meetingId' => ['nullable', 'string', 'max:100'],
        ]);

        $meetingId = null;
        if (! empty($validated['meetingId'])) {
            $meeting = ClassroomMeeting::query()
                ->where('id', $validated['meetingId'])
                ->where('classroom_id', $classroom->id)
                ->firstOrFail();
            abort_unless($meeting->isJoinable(), 422, 'This session is not joinable.');
            $meetingId = $meeting->id;
        }

        return response()->json(['ok' => true] + $this->video->mint($user, $classroom->id, $meetingId));
    }
}
