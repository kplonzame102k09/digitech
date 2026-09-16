<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Services\AttendanceSessionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceSessionService $sessions) {}

    /**
     * Cascading filter options derived from the teacher's own classrooms.
     * Each level returns only values that still exist in a classroom, so the
     * pickers always lead somewhere.
     */
    public function filters(Request $request): JsonResponse
    {
        $user = $request->user();

        $base = Classroom::query()
            ->where('teacherId', $user->user_id)
            ->select([
                'academicYear',
                'department',
                'section',
                'subject',
                DB::raw('COUNT(*) AS c'),
            ])
            ->groupBy('academicYear', 'department', 'section', 'subject')
            ->get();

        $picker = fn (string $field) => $base
            ->pluck($field)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'ok' => true,
            'academicYears' => $picker('academicYear'),
            'departments' => $picker('department'),
            'sections' => $picker('section'),
            'subjects' => $picker('subject'),
        ]);
    }

    public function classrooms(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'year' => ['nullable', 'string', 'max:32'],
            'department' => ['nullable', 'string', 'max:64'],
            'section' => ['nullable', 'string', 'max:64'],
            'subject' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Classroom::query()->where('teacherId', $user->user_id)->withCount('students');

        foreach ([
            'year' => 'academicYear',
            'department' => 'department',
            'section' => 'section',
            'subject' => 'subject',
        ] as $param => $column) {
            $value = $validated[$param] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $query->where($column, trim($value));
            }
        }

        $classrooms = $query->with('teacher')->orderByDesc('updated_at')->get();

        return response()->json([
            'ok' => true,
            'classrooms' => $classrooms->map(function (Classroom $c) {
                $data = $this->sessions->serializeClassroom($c);
                $data['students'] = $c->students()
                    ->orderBy('firstName')
                    ->orderBy('lastName')
                    ->get(['users.user_id', 'users.firstName', 'users.lastName', 'users.photo']);

                return $data;
            })->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AttendanceSession::query()
            ->whereHas('classroom', fn ($q) => $q->where('teacherId', $user->user_id));

        if ($request->has('classroomId') && trim((string) $request->input('classroomId')) !== '') {
            $query->where('classroomId', trim((string) $request->input('classroomId')));
        }

        $sessions = $query->orderByDesc('date')->orderByDesc('created_at')->get();

        return response()->json([
            'ok' => true,
            'sessions' => $sessions->map(function (AttendanceSession $session): array {
                $payload = $this->sessions->ifLocked($session);

                return [
                    'session' => $payload['session'],
                    'classroom' => $payload['classroom'],
                    'teacher' => $payload['teacher'],
                    'summary' => $payload['summary'],
                    'studentsCount' => $payload['students'] ? count($payload['students']) : 0,
                ];
            })->values(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'classroomId' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'scheduledEndTime' => ['nullable', 'date_format:H:i'],
            'autoSubmit' => ['nullable', 'boolean'],
        ]);

        $classroom = Classroom::query()->findOrFail($validated['classroomId']);
        $this->authorize('manage', $classroom);

        if ($this->sessions->hasOpenSession($classroom, $validated['date'])) {
            return response()->json([
                'ok' => false,
                'error' => 'An open attendance session already exists for this classroom on this date. Submit or cancel it first.',
            ], 422);
        }

        $session = $this->sessions->start(
            $classroom,
            $validated['date'],
            $validated['startTime'] ?? null,
            $validated['scheduledEndTime'] ?? null,
            $user,
            (bool) ($validated['autoSubmit'] ?? true),
        );

        return response()->json(['ok' => true, ...$this->sessions->load($session)]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $session = AttendanceSession::query()->findOrFail($id);
        $this->authorize('view', $session->classroom);

        return response()->json(['ok' => true, ...$this->sessions->load($session)]);
    }

    public function mark(Request $request, string $id): JsonResponse
    {
        $session = AttendanceSession::query()->findOrFail($id);
        $this->authorize('manage', $session->classroom);

        $validated = $request->validate([
            'studentId' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:Present,Late,Absent,Excused'],
            'timeIn' => ['nullable', 'date_format:H:i'],
            'timeOut' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->sessions->mark(
            $session,
            $request->user(),
            $validated['studentId'],
            $validated['status'],
            $validated['timeIn'] ?? null,
            $validated['timeOut'] ?? null,
            $validated['remarks'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'error' => $result['message'],
                'blocked' => $result['blocked'],
                'student' => $result['student'],
            ], $result['blocked'] ? 422 : 400);
        }

        $payload = $this->sessions->load($session);

        return response()->json([
            'ok' => true,
            'student' => $result['student'],
            ...$payload,
        ]);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $session = AttendanceSession::query()->findOrFail($id);
        $classroom = $session->classroom;
        $this->authorize('manage', $classroom);

        $validated = $request->validate([
            'studentId' => ['required', 'string', 'max:64'],
            'confirmed' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $adviser = $this->sessions->adviserFor($validated['studentId']);

        $isAdviser = $adviser !== null && $adviser['id'] === $user->user_id;
        $canFallback = $adviser === null && (string) $classroom->teacherId === $user->user_id;

        if (! $user->isAdmin() && ! $isAdviser && ! $canFallback) {
            return response()->json([
                'ok' => false,
                'error' => 'Only the assigned teacher can verify this student.',
            ], 403);
        }

        $result = $this->sessions->verify($session, $user, $validated['studentId'], (bool) $validated['confirmed']);

        $payload = $this->sessions->load($session);

        return response()->json([
            'ok' => true,
            'student' => $result['student'],
            ...$payload,
        ]);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $session = AttendanceSession::query()->findOrFail($id);
        $this->authorize('manage', $session->classroom);

        $payload = $this->sessions->submit($session, $request->user());

        return response()->json(['ok' => true, ...$payload]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $session = AttendanceSession::query()->findOrFail($id);
        $this->authorize('manage', $session->classroom);

        $payload = $this->sessions->cancel($session, $request->user());

        return response()->json(['ok' => true, ...$payload]);
    }

    public function export(Request $request, string $id): Response
    {
        $session = AttendanceSession::query()->findOrFail($id);
        $this->authorize('view', $session->classroom);

        $rows = Attendance::query()
            ->where('sessionId', $session->id)
            ->where('kind', Attendance::KIND_CHECK)
            ->orderBy('created_at')
            ->get();

        $studentNames = User::query()
            ->whereIn('user_id', $rows->pluck('studentId')->filter()->unique()->values())
            ->get(['user_id', 'firstName', 'lastName'])
            ->mapWithKeys(fn (User $u) => [
                $u->user_id => trim($u->firstName.' '.$u->lastName) ?: $u->user_id,
            ])
            ->all();

        $csv = $this->toCsv([
            ['Student ID', 'Name', 'Status', 'Time In', 'Time Out', 'Remarks'],
            ...$rows->map(fn (Attendance $r) => [
                $r->studentId,
                $studentNames[$r->studentId] ?? $r->studentId,
                $r->status ?? '',
                $r->timeIn ?? '',
                $r->timeOut ?? '',
                $r->remarks ?? '',
            ])->values()->all(),
        ]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attendance-'.$session->id.'.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $lines
     */
    private function toCsv(array $lines): string
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($stream, $line);
        }
        rewind($stream);

        return stream_get_contents($stream) ?: '';
    }
}
