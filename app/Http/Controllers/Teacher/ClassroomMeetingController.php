<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\ClassroomMeeting;
use App\Models\User;
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

    private function ownedClassroom(User $user, string $id): Classroom
    {
        $classroom = Classroom::query()->findOrFail($id);
        abort_unless($this->work->ownsClassroom($user, $classroom), 403);

        return $classroom;
    }

    public function index(Request $request, string $id): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);

        $meetings = ClassroomMeeting::query()
            ->where('classroom_id', $classroom->id)
            ->orderBy('starts_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ok' => true,
            'videoEnabled' => $this->video->isEnabled(),
            'meetings' => $meetings->map(fn (ClassroomMeeting $m) => $this->serialize($m))->values(),
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $meeting = ClassroomMeeting::create([
            'classroom_id' => $classroom->id,
            'title' => trim($validated['title'] ?? '') !== '' ? trim($validated['title']) : 'Live class',
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'room_name' => $this->video->roomForClassroom($classroom->id),
            'status' => ClassroomMeeting::SCHEDULED,
            'createdBy' => $request->user()->user_id,
        ]);
        // Per-session room so scheduled classes don't collide with instant room.
        $meeting->room_name = $this->video->roomForClassroom($classroom->id, $meeting->id);
        $meeting->save();

        AuditLog::record([
            'entity' => 'classroom',
            'recordId' => $classroom->id,
            'action' => 'classroom.meeting_scheduled',
            'actorId' => $request->user()->user_id,
            'notes' => "Meeting {$meeting->title} scheduled",
        ]);

        return response()->json(['ok' => true, 'meeting' => $this->serialize($meeting)], 201);
    }

    public function start(Request $request, string $id, string $meetingId): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);
        $meeting = $this->scopedMeeting($classroom, $meetingId);
        $meeting->status = ClassroomMeeting::LIVE;
        $meeting->save();

        return response()->json(['ok' => true, 'meeting' => $this->serialize($meeting)]);
    }

    public function end(Request $request, string $id, string $meetingId): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);
        $meeting = $this->scopedMeeting($classroom, $meetingId);
        $meeting->status = ClassroomMeeting::ENDED;
        $meeting->save();

        return response()->json(['ok' => true, 'meeting' => $this->serialize($meeting)]);
    }

    public function cancel(Request $request, string $id, string $meetingId): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);
        $meeting = $this->scopedMeeting($classroom, $meetingId);
        $meeting->status = ClassroomMeeting::CANCELLED;
        $meeting->save();

        return response()->json(['ok' => true, 'meeting' => $this->serialize($meeting)]);
    }

    public function destroy(Request $request, string $id, string $meetingId): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);
        $this->scopedMeeting($classroom, $meetingId)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Mint a join token. meetingId optional: instant room when omitted.
     */
    public function token(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $classroom = $this->ownedClassroom($user, $id);
        abort_unless($classroom->isActive(), 422, 'This classroom is archived.');

        $validated = $request->validate([
            'meetingId' => ['nullable', 'string', 'max:100'],
        ]);

        $meetingId = null;
        if (! empty($validated['meetingId'])) {
            $meeting = $this->scopedMeeting($classroom, $validated['meetingId']);
            abort_unless($meeting->isJoinable(), 422, 'This session is not joinable.');
            $meetingId = $meeting->id;
        }

        return response()->json(['ok' => true] + $this->video->mint($user, $classroom->id, $meetingId));
    }

    private function scopedMeeting(Classroom $classroom, string $meetingId): ClassroomMeeting
    {
        return ClassroomMeeting::query()
            ->where('id', $meetingId)
            ->where('classroom_id', $classroom->id)
            ->firstOrFail();
    }

    private function serialize(ClassroomMeeting $m): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'starts_at' => $m->starts_at?->toIso8601String(),
            'ends_at' => $m->ends_at?->toIso8601String(),
            'status' => $m->status,
            'joinable' => $m->isJoinable(),
        ];
    }
}
