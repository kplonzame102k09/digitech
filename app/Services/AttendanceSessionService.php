<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attendance session engine for the teacher funnel:
 *
 * Academic Year -> Department -> Section -> Subject -> Classroom
 *   -> Attendance Session (date + start/end + status)
 *   -> student validation (normal vs multiple/conflict)
 *   -> mark (Present/Late/Excused/Absent)
 *   -> session monitoring -> auto-submit at scheduled end -> locked.
 *
 * Session marks are `attendance.kind = 'check'` rows linked by `sessionId`.
 * Submitting locks the session only — the official `kind = 'final'` rows are
 * NOT written here. They are created when the assigned (advisory) teacher
 * reviews the day and submits it to the admin for finalization, so analytics
 * only ever count admin-finalized marks.
 */
class AttendanceSessionService
{
    public const PRESENT_LIKE = ['Present', 'Late', 'Excused'];

    public function buildSessionId(): string
    {
        return AttendanceSession::generateId();
    }

    public function buildAttendanceId(): string
    {
        return 'ATT-'.now()->year.'-'.strtoupper((string) Str::random(6));
    }

    public function hasOpenSession(Classroom $classroom, string $date): bool
    {
        return AttendanceSession::query()
            ->where('classroomId', $classroom->id)
            ->whereDate('date', $date)
            ->where('status', AttendanceSession::OPEN)
            ->exists();
    }

    public function start(Classroom $classroom, string $date, ?string $startTime, ?string $scheduledEndTime, User $actor, bool $autoSubmit = true): AttendanceSession
    {
        return AttendanceSession::create([
            'classroomId' => $classroom->id,
            'date' => $date,
            'startTime' => $startTime ?: now()->format('H:i:s'),
            'scheduledEndTime' => $scheduledEndTime ?: ($classroom->endTime ?? null),
            'status' => AttendanceSession::OPEN,
            'autoSubmit' => $autoSubmit,
            'startedBy' => $actor->user_id,
        ]);
    }

    /**
     * The classroom roster as student User models (integer `id` keys are used
     * for pivot lookups, string `user_id` for attendance rows).
     */
    public function roster(Classroom $classroom): Collection
    {
        return $classroom->students()
            ->orderBy('firstName')
            ->orderBy('lastName')
            ->get(['users.id', 'users.user_id', 'users.firstName', 'users.lastName', 'users.email', 'users.photo']);
    }

    /**
     * The student's assigned (advising) teacher from the latest enrollment,
     * or null when unassigned. This teacher later reviews the day's checks and
     * submits the official finals for admin finalization.
     */
    public function adviserFor(string $studentId): ?array
    {
        $adviserId = Enrollment::query()
            ->where('studentId', $studentId)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->value('assignedTeacherId');

        if ($adviserId === null || trim((string) $adviserId) === '') {
            return null;
        }

        $teacher = User::query()->where('user_id', (string) $adviserId)
            ->first(['user_id', 'firstName', 'lastName', 'photo']);

        if ($teacher) {
            return [
                'id' => $teacher->user_id,
                'name' => trim($teacher->firstName.' '.$teacher->lastName) ?: $teacher->user_id,
                'photo' => $teacher->photo,
            ];
        }

        return ['id' => (string) $adviserId, 'name' => (string) $adviserId, 'photo' => null];
    }

    /**
     * Other active classrooms each roster member belongs to, so schedule
     * overlap can be detected (student in two rooms at the same time).
     *
     * @return array<int, object>
     */
    private function otherRoomMemberships(Collection $roster, Classroom $classroom): array
    {
        if ($roster->isEmpty()) {
            return [];
        }

        return DB::table('classroom_student as cs')
            ->join('classrooms as c', 'c.id', '=', 'cs.classroom_id')
            ->whereIn('cs.student_id', $roster->pluck('id'))
            ->where('cs.classroom_id', '!=', $classroom->id)
            ->where('c.status', Classroom::ACTIVE)
            ->select(['cs.student_id as studentKey', 'c.id', 'c.name', 'c.subject', 'c.startTime', 'c.endTime', 'c.teacherId'])
            ->get()
            ->all();
    }

    private function timeOverlaps(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        if ($startA === null || $endA === null || $startB === null || $endB === null || $startA === '' || $endA === '' || $startB === '' || $endB === '') {
            return false;
        }

        // Zero-padded 24h "HH:MM:SS" strings compare lexicographically.
        return (string) $startA < (string) $endB && (string) $startB < (string) $endA;
    }

    /**
     * student user_id => conflicting classroom descriptors (name + subject).
     *
     * @return array<string, array<int, array{id:string,name:string,subject:?string}>>
     */
    public function buildConflicts(Classroom $classroom, Collection $roster): array
    {
        $keyToUserId = $roster->mapWithKeys(fn (User $u) => [(string) $u->id => (string) $u->user_id]);
        $grouped = [];

        foreach ($this->otherRoomMemberships($roster, $classroom) as $row) {
            $grouped[$row->studentKey] ??= [];
            $grouped[$row->studentKey][] = $row;
        }

        $conflicts = [];
        foreach ($roster as $student) {
            $list = [];
            foreach ($grouped[$student->id] ?? [] as $other) {
                if ($this->timeOverlaps($classroom->startTime, $classroom->endTime, $other->startTime, $other->endTime)) {
                    $list[] = [
                        'id' => (string) $other->id,
                        'name' => (string) $other->name,
                        'subject' => $other->subject !== null ? (string) $other->subject : null,
                    ];
                }
            }
            if ($list !== []) {
                $conflicts[$keyToUserId[(string) $student->id]] = $list;
            }
        }

        return $conflicts;
    }

    /**
     * The session's live check row for a student. Prefers a row already bound
     * to this session; otherwise adopts the legacy hand-marked check so the
     * (studentId, date, classroomId, session, kind) unique key always holds.
     */
    public function sessionCheckRow(AttendanceSession $session, string $studentId): ?Attendance
    {
        return Attendance::query()
            ->where('studentId', $studentId)
            ->whereDate('date', $session->date->format('Y-m-d'))
            ->where('classroomId', $session->classroomId)
            ->where('session', 1)
            ->where('kind', Attendance::KIND_CHECK)
            ->orderByRaw('(sessionId IS NOT NULL) DESC')
            ->latest()
            ->first();
    }

    /** @return array<string, Attendance> */
    private function checksByStudent(AttendanceSession $session): array
    {
        return Attendance::query()
            ->where('sessionId', $session->id)
            ->where('kind', Attendance::KIND_CHECK)
            ->get()
            ->keyBy('studentId')
            ->all();
    }

    /** @return array<string, Attendance> */
    private function finalsByStudent(AttendanceSession $session): array
    {
        return Attendance::query()
            ->where('sessionId', $session->id)
            ->where('kind', Attendance::KIND_FINAL)
            ->get()
            ->keyBy('studentId')
            ->all();
    }

    public function serializeSession(AttendanceSession $session): array
    {
        $end = $session->scheduledEndTime !== null ? (string) $session->scheduledEndTime : null;
        $due = $session->isOpen() && $end !== null && now()->format('H:i:s') >= $end;

        return [
            'id' => $session->id,
            'classroomId' => $session->classroomId,
            'date' => $session->date->format('Y-m-d'),
            'startTime' => $session->startTime,
            'scheduledEndTime' => $end,
            'actualEndTime' => $session->actualEndTime,
            'status' => $session->status,
            'autoSubmit' => (bool) $session->autoSubmit,
            'startedBy' => $session->startedBy,
            'lockedBy' => $session->lockedBy,
            'lockedAt' => $session->lockedAt?->toIso8601String(),
            'due' => $due,
        ];
    }

    public function serializeClassroom(Classroom $classroom): array
    {
        $studentsCount = $classroom->students_count ?? $classroom->students()->count();

        $teacher = $classroom->relationLoaded('teacher')
            ? [
                'id' => $classroom->teacher?->user_id,
                'name' => $classroom->teacher?->firstName
                    ? trim($classroom->teacher->firstName.' '.$classroom->teacher->lastName)
                    : null,
            ]
            : null;

        return [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'subject' => $classroom->subject,
            'academicYear' => $classroom->academicYear,
            'department' => $classroom->department,
            'section' => $classroom->section,
            'startTime' => $classroom->startTime,
            'endTime' => $classroom->endTime,
            'teacherId' => $classroom->teacherId,
            'teacher' => $teacher,
            'studentsCount' => $studentsCount,
        ];
    }

    public function rosterEntry(AttendanceSession $session, Classroom $classroom, User $student, array $conflicts, ?Attendance $row, ?Attendance $finalRow): array
    {
        $requires = $conflicts !== [];
        $verificationStatus = $row?->verificationStatus;
        $confirmed = $verificationStatus === Attendance::VERIFICATION_CONFIRMED;

        return [
            'id' => $student->user_id,
            'firstName' => $student->firstName,
            'lastName' => $student->lastName,
            'email' => $student->email,
            'photo' => $student->photo,
            'classroomId' => $classroom->id,
            'adviser' => $this->adviserFor((string) $student->user_id),
            'requiresVerification' => $requires,
            'verificationStatus' => $verificationStatus ?? ($requires ? Attendance::VERIFICATION_PENDING : null),
            'verifiedBy' => $row?->verifiedBy,
            'conflicts' => array_values($conflicts),
            'status' => $row?->status ?? ($finalRow?->status ?? null),
            'timeIn' => $row?->timeIn,
            'timeOut' => $row?->timeOut,
            'remarks' => $row?->remarks ?? '',
            'recordId' => $row?->id,
            'markable' => $session->isOpen() && (! $requires || $confirmed),
        ];
    }

    /** @return array{total:int,present:int,late:int,excused:int,absent:int,unmarked:int,pending:int} */
    public function summaryEntries(array $students): array
    {
        $total = count($students);
        $present = $late = $excused = $absent = $unmarked = $pending = 0;

        foreach ($students as $s) {
            if ($s['requiresVerification'] && $s['verificationStatus'] === Attendance::VERIFICATION_PENDING) {
                $pending++;
            }
            switch ($s['status']) {
                case Attendance::PRESENT: $present++;
                    break;
                case Attendance::LATE: $late++;
                    break;
                case Attendance::EXCUSED: $excused++;
                    break;
                case Attendance::ABSENT: $absent++;
                    break;
                default: $unmarked++;
            }
        }

        return compact('total', 'present', 'late', 'excused', 'absent', 'unmarked', 'pending');
    }

    public function load(AttendanceSession $session): array
    {
        $session->refresh();
        $this->autoSubmitIfDue($session);
        $session->load('classroom.teacher');

        $classroom = $session->classroom;
        $roster = $this->roster($classroom);
        $conflicts = $this->buildConflicts($classroom, $roster);
        $checks = $this->checksByStudent($session);
        $finals = $this->finalsByStudent($session);

        $students = $roster->map(fn (User $s) => $this->rosterEntry(
            $session,
            $classroom,
            $s,
            $conflicts[(string) $s->user_id] ?? [],
            $checks[(string) $s->user_id] ?? null,
            $finals[(string) $s->user_id] ?? null,
        ))->values()->all();

        return [
            'session' => $this->serializeSession($session),
            'classroom' => $this->serializeClassroom($classroom),
            'teacher' => [
                'id' => $classroom->teacher?->user_id,
                'name' => $classroom->teacher ? trim($classroom->teacher->firstName.' '.$classroom->teacher->lastName) : $classroom->teacherId,
            ],
            'students' => $students,
            'summary' => $this->summaryEntries($students),
            'locked' => ! $session->isOpen(),
        ];
    }

    /**
     * @return array{ok:bool,student:?array<string,mixed>,message:?string,blocked:bool}
     */
    public function mark(AttendanceSession $session, User $actor, string $studentId, string $status, ?string $timeIn, ?string $timeOut, ?string $remarks): array
    {
        if (! $session->isOpen()) {
            return ['ok' => false, 'student' => null, 'message' => 'Attendance session is locked.', 'blocked' => true];
        }

        $classroom = $session->classroom;
        $roster = $this->roster($classroom);
        $student = $roster->firstWhere('user_id', $studentId);

        if (! $student) {
            return ['ok' => false, 'student' => null, 'message' => 'Student is not part of this classroom.', 'blocked' => false];
        }

        $conflicts = $this->buildConflicts($classroom, $roster)[$studentId] ?? [];
        $row = $this->sessionCheckRow($session, $studentId);
        $verificationStatus = $row?->verificationStatus;

        if ($conflicts !== [] && $verificationStatus !== Attendance::VERIFICATION_CONFIRMED) {
            return [
                'ok' => false,
                'student' => $this->rosterEntry($session, $classroom, $student, $conflicts, $row, null),
                'message' => 'Multiple-class conflict — the assigned teacher must verify this student before marking.',
                'blocked' => true,
            ];
        }

        $attributes = [
            'studentId' => $studentId,
            'date' => $session->date->format('Y-m-d'),
            'subject' => $classroom->subject,
            'classroomId' => $classroom->id,
            'classroomName' => $classroom->name,
            'sessionId' => $session->id,
            'session' => 1,
            'kind' => Attendance::KIND_CHECK,
            'status' => $status,
            'timeIn' => $timeIn,
            'timeOut' => $timeOut,
            'remarks' => $remarks ?? '',
            'recordedBy' => $actor->user_id,
            'isDraft' => false,
            'isLocked' => false,
            'requiresVerification' => $conflicts !== [],
            'verificationStatus' => $verificationStatus,
            'verifiedBy' => $row?->verifiedBy,
            'verifiedAt' => $row?->verifiedAt,
        ];

        if ($row) {
            $row->fill($attributes)->save();
        } else {
            $attributes['id'] = $this->buildAttendanceId();
            $row = Attendance::create($attributes);
        }

        return [
            'ok' => true,
            'student' => $this->rosterEntry($session, $classroom, $student, $conflicts, $row, null),
            'message' => null,
            'blocked' => false,
        ];
    }

    public function verify(AttendanceSession $session, User $actor, string $studentId, bool $confirmed): array
    {
        if (! $session->isOpen()) {
            throw new \RuntimeException('Attendance session is locked.');
        }

        $classroom = $session->classroom;
        $student = $this->roster($classroom)->firstWhere('user_id', $studentId);

        if (! $student) {
            throw new \RuntimeException('Student is not part of this classroom.');
        }

        $row = $this->sessionCheckRow($session, $studentId);
        $attributes = [
            'studentId' => $studentId,
            'date' => $session->date->format('Y-m-d'),
            'subject' => $classroom->subject,
            'classroomId' => $classroom->id,
            'classroomName' => $classroom->name,
            'sessionId' => $session->id,
            'session' => 1,
            'kind' => Attendance::KIND_CHECK,
            'requiresVerification' => true,
            'verificationStatus' => $confirmed ? Attendance::VERIFICATION_CONFIRMED : Attendance::VERIFICATION_REJECTED,
            'verifiedBy' => $actor->user_id,
            'verifiedAt' => now(),
            'recordedBy' => $row?->recordedBy ?? $actor->user_id,
        ];

        if ($row) {
            $row->fill($attributes)->save();
        } else {
            $attributes['id'] = $this->buildAttendanceId();
            $row = Attendance::create($attributes);
        }

        return [
            'ok' => true,
            'student' => $this->rosterEntry($session, $classroom, $student, $this->buildConflicts($classroom, $this->roster($classroom))[$studentId] ?? [], $row, null),
        ];
    }

    public function autoSubmitIfDue(AttendanceSession $session): bool
    {
        if (! $session->isOpen() || ! $session->autoSubmit || $session->scheduledEndTime === null) {
            return false;
        }

        if (now()->format('H:i:s') < (string) $session->scheduledEndTime) {
            return false;
        }

        $this->submit($session);

        return true;
    }

    public function submit(AttendanceSession $session, ?User $actor = null): array
    {
        $session->refresh();

        if ($session->isLocked()) {
            return $this->ifLocked($session);
        }

        if ($session->isCancelled()) {
            return $this->ifLocked($session);
        }

        $classroom = $session->classroom;
        $date = $session->date->format('Y-m-d');
        $actor ??= User::query()->where('user_id', $session->startedBy)->first();

        DB::transaction(function () use ($session, $classroom, $actor): void {
            // Lock only. Official finals are deferred to the advisory review +
            // admin finalization flow so analytics never count provisional marks.
            $session->update([
                'status' => AttendanceSession::LOCKED,
                'actualEndTime' => now()->format('H:i:s'),
                'lockedAt' => now(),
                'lockedBy' => $actor?->user_id ?? $classroom->teacherId,
            ]);
        });

        AuditLog::record([
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'recordId' => (string) $session->id,
            'action' => 'attendance.session_submitted',
            'actorId' => $actor?->user_id ?? $classroom->teacherId,
            'notes' => "Attendance session submitted and locked for {$classroom->name} on {$date}.",
        ]);

        return $this->ifLocked($session);
    }

    public function cancel(AttendanceSession $session, User $actor): array
    {
        if (! $session->isOpen()) {
            throw new \RuntimeException('Only an open attendance session can be cancelled.');
        }

        $session->update([
            'status' => AttendanceSession::CANCELLED,
            'lockedAt' => now(),
            'lockedBy' => $actor->user_id,
        ]);

        AuditLog::record([
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'recordId' => (string) $session->id,
            'action' => 'attendance.session_cancelled',
            'actorId' => $actor->user_id,
            'notes' => "Attendance session for {$session->classroom->name} on {$session->date->format('Y-m-d')} cancelled.",
        ]);

        return $this->ifLocked($session);
    }

    public function ifLocked(AttendanceSession $session): array
    {
        $session->refresh();
        $session->load('classroom.teacher');
        $classroom = $session->classroom;
        $roster = $this->roster($classroom);
        $conflicts = $this->buildConflicts($classroom, $roster);
        $checks = $this->checksByStudent($session);
        $finals = $this->finalsByStudent($session);

        $students = $roster->map(fn (User $s) => $this->rosterEntry(
            $session,
            $classroom,
            $s,
            $conflicts[(string) $s->user_id] ?? [],
            $checks[(string) $s->user_id] ?? null,
            $finals[(string) $s->user_id] ?? null,
        ))->values()->all();

        return [
            'session' => $this->serializeSession($session),
            'classroom' => $this->serializeClassroom($classroom),
            'teacher' => [
                'id' => $classroom->teacher?->user_id,
                'name' => $classroom->teacher ? trim($classroom->teacher->firstName.' '.$classroom->teacher->lastName) : $classroom->teacherId,
            ],
            'students' => $students,
            'summary' => $this->summaryEntries($students),
            'locked' => true,
        ];
    }

    /**
     * Past sessions for a classroom (newest first) with their summaries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyFor(Classroom $classroom): array
    {
        return AttendanceSession::query()
            ->where('classroomId', $classroom->id)
            ->where('status', '!=', AttendanceSession::OPEN)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AttendanceSession $session): array {
                $payload = $this->ifLocked($session);

                return array_merge($payload, ['summary' => $payload['summary']]);
            })
            ->values()
            ->all();
    }
}
