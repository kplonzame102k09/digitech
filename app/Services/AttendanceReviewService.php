<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePackage;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Advisory attendance review + admin finalization.
 *
 * Flow: CLASSROOM teachers record per-classroom checks during sessions
 * (kind='check'; sessions lock without writing finals) -> the assigned
 * (advisory) teacher of each student's latest enrollment reviews the whole
 * day, edits a single official final per student (kind='final'), SUBMITS the
 * day to the admin (attendance_packages, status=submitted) -> the admin
 * either RETURNs it (status=returned) for correction -> the adviser
 * re-edits and resubmits, or FINALIZEs it (status=finalized), which stamps
 * every final row with finalizedAt/finalizedBy and locks it.
 *
 * Only finalized finals count in analytics and surface to student/parent
 * views (see Attendance::scopeFinalized / AttendanceAnalyticsService).
 */
class AttendanceReviewService
{
    public function __construct(private AttendanceSessionService $sessions) {}

    public function buildAttendanceId(): string
    {
        return 'ATT-'.now()->year.'-'.strtoupper((string) Str::random(6));
    }

    /**
     * The student's current enrollment (latest by updated, then created,
     * then id) — mirrors the adviser resolution used across the app.
     */
    public function latestEnrollment(string $studentId): ?Enrollment
    {
        return Enrollment::query()
            ->where('studentId', $studentId)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function isAdviserFor(string $studentId, string $adviserId): bool
    {
        $enrollment = $this->latestEnrollment($studentId);

        return $enrollment !== null
            && trim((string) $enrollment->assignedTeacherId) !== ''
            && (string) $enrollment->assignedTeacherId === $adviserId;
    }

    /**
     * user_ids of every student whose latest enrollment assigns `$adviserId`.
     *
     * @return array<int, string>
     */
    public function adviseeUserIds(string $adviserId): array
    {
        return Enrollment::query()
            ->select('studentId')
            ->where('assignedTeacherId', $adviserId)
            ->whereNotNull('studentId')
            ->whereRaw('id = (select e2.id from enrollments e2 where e2.studentId = enrollments.studentId order by e2.updated_at desc, e2.created_at desc, e2.id desc limit 1)')
            ->pluck('studentId')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function packageFor(string $adviserId, string $date): ?AttendancePackage
    {
        return AttendancePackage::query()
            ->where('adviserId', $adviserId)
            ->whereDate('date', $date)
            ->first();
    }

    private function finalFor(string $studentId, string $date): ?Attendance
    {
        return Attendance::query()
            ->where('studentId', $studentId)
            ->whereDate('date', $date)
            ->where('classroomId', '')
            ->where('session', 1)
            ->where('kind', Attendance::KIND_FINAL)
            ->first();
    }

    /**
     * Per-student per-day official final, upserting when missing so the
     * (studentId, date, classroomId, session, kind) unique key never splits.
     */
    private function upsertFinal(string $studentId, string $date, string $status, string $recordedBy, bool $editable = true): Attendance
    {
        $record = $this->finalFor($studentId, $date);

        $firstCheck = Attendance::query()
            ->where('studentId', $studentId)
            ->whereDate('date', $date)
            ->where('kind', Attendance::KIND_CHECK)
            ->orderBy('created_at')
            ->first();

        $attributes = [
            'studentId' => $studentId,
            'date' => $date,
            'subject' => $firstCheck?->subject ?: ($firstCheck?->classroomName ?: 'General'),
            'classroomId' => '',
            'classroomName' => null,
            'sessionId' => null,
            'session' => 1,
            'kind' => Attendance::KIND_FINAL,
            'status' => $status,
            'recordedBy' => $recordedBy,
            'isDraft' => false,
            'isLocked' => ! $editable,
        ];

        if ($record) {
            $record->fill($attributes)->save();

            return $record;
        }

        $attributes['id'] = $this->buildAttendanceId();
        $attributes['finalizedAt'] = null;
        $attributes['finalizedBy'] = null;

        return Attendance::create($attributes);
    }

    /**
     * Resolve a final status for a student from their classroom marks:
     * anything present/late/excused counts as Present; otherwise Absent.
     */
    private function defaultFinalStatus(string $studentId, string $date): string
    {
        $hasPresent = Attendance::query()
            ->where('studentId', $studentId)
            ->whereDate('date', $date)
            ->where('kind', Attendance::KIND_CHECK)
            ->whereIn('status', AttendanceSessionService::PRESENT_LIKE)
            ->exists();

        return $hasPresent ? Attendance::PRESENT : Attendance::ABSENT;
    }

    /**
     * @return array{date:string,adviser:array{id:string,name:string},package:?array,students:array<int,array<string,mixed>>}
     */
    public function reviewFor(User $adviser, string $date): array
    {
        $package = $this->packageFor($adviser->user_id, $date);
        $editable = $package === null || $package->isReturned();
        $students = $this->assembleStudents($adviser->user_id, $date, $editable);

        return [
            'date' => $date,
            'adviser' => [
                'id' => $adviser->user_id,
                'name' => trim($adviser->firstName.' '.$adviser->lastName) ?: $adviser->user_id,
            ],
            'package' => $package ? $this->serializePackage($package) : null,
            'students' => $students,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assembleStudents(string $adviserId, string $date, bool $editable): array
    {
        $ids = $this->adviseeUserIds($adviserId);

        if ($ids === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('user_id', $ids)
            ->orderBy('firstName')
            ->orderBy('lastName')
            ->get(['user_id', 'firstName', 'lastName', 'photo', 'email']);

        $checks = Attendance::query()
            ->whereIn('studentId', $ids)
            ->whereDate('date', $date)
            ->where('kind', Attendance::KIND_CHECK)
            ->orderBy('created_at')
            ->get();

        $finalRows = Attendance::query()
            ->whereIn('studentId', $ids)
            ->whereDate('date', $date)
            ->where('classroomId', '')
            ->where('session', 1)
            ->where('kind', Attendance::KIND_FINAL)
            ->get()
            ->keyBy('studentId');

        $classroomIds = $checks->pluck('classroomId')->filter(fn ($v) => is_string($v) && $v !== '')->unique()->all();
        $classrooms = collect($classroomIds)
            ->mapWithKeys(fn (string $id) => [$id => Classroom::query()->with('teacher')->find($id)]);

        $enrollments = collect($ids)->mapWithKeys(
            fn (string $id) => [$id => $this->latestEnrollment($id)]
        );

        return $users->map(function (User $user) use ($editable, $checks, $finalRows, $classrooms, $enrollments): array {
            $sid = (string) $user->user_id;
            $mine = $checks->where('studentId', $sid)->values();
            $final = $finalRows->get($sid);
            $enrollment = $enrollments->get($sid);

            return [
                'id' => $sid,
                'name' => trim($user->firstName.' '.$user->lastName) ?: $sid,
                'photo' => $user->photo,
                'email' => $user->email,
                'section' => $enrollment?->assignedSection,
                'checks' => $mine->map(function (Attendance $row) use ($classrooms): array {
                    $classroom = $row->classroomId !== '' && $row->classroomId !== null
                        ? ($classrooms[$row->classroomId] ?? null)
                        : null;

                    return [
                        'classroomId' => $row->classroomId ?? '',
                        'classroomName' => $row->classroomName ?: ($classroom?->name ?: 'Classroom'),
                        'subject' => $row->subject ?: ($classroom?->subject ?: null),
                        'teacherId' => $classroom?->teacherId,
                        'teacherName' => $classroom?->teacher
                            ? trim($classroom->teacher->firstName.' '.$classroom->teacher->lastName)
                            : $classroom?->teacherId,
                        'status' => $row->status,
                        'timeIn' => $row->timeIn,
                        'timeOut' => $row->timeOut,
                        'sessionId' => $row->sessionId,
                        'recordedBy' => $row->recordedBy,
                    ];
                })->values()->all(),
                'final' => $final ? [
                    'status' => $final->status,
                    'recordedBy' => $final->recordedBy,
                    'isLocked' => (bool) $final->isLocked,
                    'finalizedAt' => $final->finalizedAt?->toIso8601String(),
                    'finalizedBy' => $final->finalizedBy,
                ] : null,
                'editable' => $editable,
            ];
        })->values()->all();
    }

    private function serializePackage(AttendancePackage $package): array
    {
        return [
            'id' => $package->id,
            'adviserId' => $package->adviserId,
            'date' => $package->date->format('Y-m-d'),
            'status' => $package->status,
            'submittedAt' => $package->submittedAt?->toIso8601String(),
            'submittedBy' => $package->submittedBy,
            'finalizedAt' => $package->finalizedAt?->toIso8601String(),
            'finalizedBy' => $package->finalizedBy,
            'returnedAt' => $package->returnedAt?->toIso8601String(),
            'returnedBy' => $package->returnedBy,
            'returnReason' => $package->returnReason,
        ];
    }

    /**
     * Adviser edits a single whole-day official status for one advisee.
     * Blocked once the day has been submitted to the admin (or finalized).
     *
     * @return array{ok:bool,student:?array<string,mixed>,message:?string}
     */
    public function setFinal(User $adviser, string $date, string $studentId, string $status): array
    {
        if (! in_array($status, Attendance::$statuses, true)) {
            return ['ok' => false, 'student' => null, 'message' => 'Unknown status.'];
        }

        if (! $this->isAdviserFor($studentId, $adviser->user_id)) {
            return ['ok' => false, 'student' => null, 'message' => 'This learner is not assigned to you.'];
        }

        $package = $this->packageFor($adviser->user_id, $date);
        if ($package !== null && ! $package->isReturned()) {
            return ['ok' => false, 'student' => null, 'message' => 'The day was already submitted to the admin. It can no longer be edited.'];
        }

        $this->upsertFinal($studentId, $date, $status, $adviser->user_id, true);

        AuditLog::record([
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'recordId' => $studentId,
            'action' => 'attendance.review_edited',
            'actorId' => $adviser->user_id,
            'notes' => "Adviser set the {$date} official attendance for {$studentId} to {$status}.",
        ]);

        return [
            'ok' => true,
            'student' => $this->assembleStudents($adviser->user_id, $date, true),
            'message' => null,
        ];
    }

    /**
     * SUBMIT the reviewed day to the admin. Writes any missing finals
     * (defaulting unmarked students to Absent) and records/updates the
     * package. Idempotent — resubmitting refreshes the submission stamp.
     *
     * @return array{ok:bool,package:?array,message:?string}
     */
    public function submit(User $adviser, string $date): array
    {
        $ids = $this->adviseeUserIds($adviser->user_id);

        if ($ids === []) {
            return ['ok' => false, 'package' => null, 'message' => 'You have no assigned learners yet.'];
        }

        $existing = $this->packageFor($adviser->user_id, $date);
        if ($existing?->isFinalized()) {
            return ['ok' => false, 'package' => null, 'message' => 'This day is already finalized and cannot be resubmitted.'];
        }

        DB::transaction(function () use ($adviser, $date, $ids): void {
            foreach ($ids as $studentId) {
                $final = $this->finalFor($studentId, $date);
                if ($final === null) {
                    $this->upsertFinal($studentId, $date, $this->defaultFinalStatus($studentId, $date), $adviser->user_id, true);
                }
            }

            $package = $this->packageFor($adviser->user_id, $date);
            $attributes = [
                'status' => AttendancePackage::SUBMITTED,
                'submittedAt' => now(),
                'submittedBy' => $adviser->user_id,
            ];

            if ($package) {
                $package->fill($attributes)->save();
                // A resubmission (returned -> submitted) clears the last return.
                if ($package->wasChanged('status')) {
                    $package->forceFill([
                        'returnedAt' => null,
                        'returnedBy' => null,
                        'returnReason' => null,
                    ])->save();
                }
            } else {
                AttendancePackage::create($attributes + ['adviserId' => $adviser->user_id, 'date' => $date]);
            }
        });

        AuditLog::record([
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'recordId' => $date,
            'action' => 'attendance.package_submitted',
            'actorId' => $adviser->user_id,
            'notes' => "Attendance package for {$adviser->user_id} on {$date} submitted for admin review.",
        ]);

        $package = $this->packageFor($adviser->user_id, $date);

        return ['ok' => true, 'package' => $this->serializePackage($package), 'message' => null];
    }

    /**
     * Packages awaiting or already finalized by the admin (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function adminPackages(): array
    {
        return AttendancePackage::query()
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AttendancePackage $package): array {
                $data = $this->serializePackage($package);
                $data['adviser'] = $this->adviserDescriptor($package->adviserId);
                $data['summary'] = $this->packageSummary($package->adviserId, $package->date->format('Y-m-d'));

                return $data;
            })
            ->values()
            ->all();
    }

    private function adviserDescriptor(string $adviserId): array
    {
        $user = User::query()->where('user_id', $adviserId)->first(['user_id', 'firstName', 'lastName', 'photo']);

        if (! $user) {
            return ['id' => $adviserId, 'name' => $adviserId, 'photo' => null];
        }

        return [
            'id' => $user->user_id,
            'name' => trim($user->firstName.' '.$user->lastName) ?: $user->user_id,
            'photo' => $user->photo,
        ];
    }

    /**
     * Present/absent/late/excused tallies for a package's advisees on its
     * date (from the official final rows).
     *
     * @return array{students:int,present:int,late:int,excused:int,absent:int,unmarked:int,rate:float}
     */
    private function packageSummary(string $adviserId, string $date): array
    {
        $ids = $this->adviseeUserIds($adviserId);

        if ($ids === []) {
            return ['students' => 0, 'present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0, 'unmarked' => 0, 'rate' => 0.0];
        }

        $rows = Attendance::query()
            ->whereIn('studentId', $ids)
            ->whereDate('date', $date)
            ->where('kind', Attendance::KIND_FINAL)
            ->get(['status']);

        $students = count($ids);
        $present = $rows->whereIn('status', AttendanceSessionService::PRESENT_LIKE)->count();
        $late = $rows->where('status', Attendance::LATE)->count();
        $excused = $rows->where('status', Attendance::EXCUSED)->count();
        $absent = $rows->where('status', Attendance::ABSENT)->count();
        $counted = $rows->count();

        return [
            'students' => $students,
            'present' => $present,
            'late' => $late,
            'excused' => $excused,
            'absent' => $absent,
            'unmarked' => max(0, $students - $counted),
            'rate' => $counted > 0 ? round($present / $counted * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array{package:array<string,mixed>,students:array<int,array<string,mixed>>}
     */
    public function packageDetail(AttendancePackage $package): array
    {
        $data = $this->serializePackage($package);
        $data['adviser'] = $this->adviserDescriptor($package->adviserId);
        $data['summary'] = $this->packageSummary($package->adviserId, $package->date->format('Y-m-d'));

        $date = $package->date->format('Y-m-d');
        $ids = $this->adviseeUserIds($package->adviserId);

        $users = User::query()
            ->whereIn('user_id', $ids)
            ->orderBy('firstName')
            ->orderBy('lastName')
            ->get(['user_id', 'firstName', 'lastName', 'photo']);
        $finalMap = Attendance::query()
            ->whereIn('studentId', $ids)
            ->whereDate('date', $date)
            ->where('classroomId', '')
            ->where('kind', Attendance::KIND_FINAL)
            ->get()
            ->keyBy('studentId');
        $checks = Attendance::query()
            ->whereIn('studentId', $ids)
            ->whereDate('date', $date)
            ->where('kind', Attendance::KIND_CHECK)
            ->get()
            ->groupBy('studentId');
        $enrollments = collect($ids)->mapWithKeys(fn (string $id) => [$id => $this->latestEnrollment($id)]);

        $classroomIds = $checks->flatten(1)->pluck('classroomId')->filter(fn ($v) => is_string($v) && $v !== '')->unique()->all();
        $classrooms = collect($classroomIds)->mapWithKeys(fn (string $id) => [$id => Classroom::query()->with('teacher')->find($id)]);

        return [
            'package' => $data,
            'students' => $users->map(function (User $user) use ($finalMap, $checks, $classrooms, $enrollments): array {
                $sid = (string) $user->user_id;
                $final = $finalMap->get($sid);

                return [
                    'id' => $sid,
                    'name' => trim($user->firstName.' '.$user->lastName) ?: $sid,
                    'photo' => $user->photo,
                    'section' => $enrollments->get($sid)?->assignedSection,
                    'checks' => collect($checks[$sid] ?? [])->map(function (Attendance $row) use ($classrooms): array {
                        $classroom = $row->classroomId !== '' && $row->classroomId !== null
                            ? ($classrooms[$row->classroomId] ?? null)
                            : null;

                        return [
                            'classroomId' => $row->classroomId ?? '',
                            'classroomName' => $row->classroomName ?: ($classroom?->name ?: 'Classroom'),
                            'subject' => $row->subject ?: ($classroom?->subject ?: null),
                            'teacherId' => $classroom?->teacherId,
                            'teacherName' => $classroom?->teacher
                                ? trim($classroom->teacher->firstName.' '.$classroom->teacher->lastName)
                                : $classroom?->teacherId,
                            'status' => $row->status,
                            'timeIn' => $row->timeIn,
                            'timeOut' => $row->timeOut,
                        ];
                    })->values()->all(),
                    'final' => $final ? [
                        'status' => $final->status,
                        'recordedBy' => $final->recordedBy,
                        'isLocked' => (bool) $final->isLocked,
                        'finalizedAt' => $final->finalizedAt?->toIso8601String(),
                        'finalizedBy' => $final->finalizedBy,
                    ] : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * ADMIN RETURN: send a submitted package back to its adviser for
     * correction. The adviser can then re-edit every final and resubmit the
     * day; resubmitting moves the package back to `submitted`.
     *
     * @return array{ok:bool,detail:?array,message:?string}
     */
    public function returnPackage(User $admin, AttendancePackage $package, string $reason): array
    {
        if (! $package->isSubmitted()) {
            return ['ok' => false, 'detail' => null, 'message' => 'Only a submitted package can be returned to the adviser.'];
        }

        DB::transaction(function () use ($admin, $package, $reason): void {
            // Unlock every advisee's final so the adviser can re-edit once
            // more without the row-level lock left by a past finalization.
            Attendance::query()
                ->whereIn('studentId', $this->adviseeUserIds($package->adviserId))
                ->whereDate('date', $package->date->format('Y-m-d'))
                ->where('classroomId', '')
                ->where('session', 1)
                ->where('kind', Attendance::KIND_FINAL)
                ->update(['isLocked' => false]);

            $package->update([
                'status' => AttendancePackage::RETURNED,
                'returnedAt' => now(),
                'returnedBy' => $admin->user_id,
                'returnReason' => $reason,
            ]);
        });

        AuditLog::record([
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'recordId' => (string) $package->id,
            'action' => 'attendance.package_returned',
            'actorId' => $admin->user_id,
            'notes' => "Attendance package {$package->id} returned to the adviser for correction: {$reason}",
        ]);

        $package->refresh();

        return [
            'ok' => true,
            'detail' => $this->packageDetail($package),
            'message' => null,
        ];
    }

    /**
     * ADMIN FINALIZE: locks the package and stamps every advisee's final.
     *
     * @return array{ok:bool,detail:?array,message:?string}
     */
    public function finalize(User $admin, AttendancePackage $package): array
    {
        if ($package->isFinalized()) {
            return ['ok' => false, 'detail' => null, 'message' => 'This package is already finalized.'];
        }

        $ids = $this->adviseeUserIds($package->adviserId);
        $date = $package->date->format('Y-m-d');

        DB::transaction(function () use ($admin, $package, $ids, $date): void {
            foreach ($ids as $studentId) {
                if ($this->finalFor($studentId, $date) === null) {
                    $this->upsertFinal($studentId, $date, $this->defaultFinalStatus($studentId, $date), $package->adviserId, false);
                }

                Attendance::query()
                    ->where('studentId', $studentId)
                    ->whereDate('date', $date)
                    ->where('classroomId', '')
                    ->where('session', 1)
                    ->where('kind', Attendance::KIND_FINAL)
                    ->update([
                        'finalizedAt' => now(),
                        'finalizedBy' => $admin->user_id,
                        'isLocked' => true,
                    ]);
            }

            $package->update([
                'status' => AttendancePackage::FINALIZED,
                'finalizedAt' => now(),
                'finalizedBy' => $admin->user_id,
            ]);
        });

        $counted = $ids ? count($ids) : 0;
        AuditLog::record([
            'entity' => AuditLog::ENTITY_ATTENDANCE,
            'recordId' => (string) $package->id,
            'action' => 'attendance.package_finalized',
            'actorId' => $admin->user_id,
            'notes' => "Attendance package {$package->id} finalized ({$counted} learners on {$date}).",
        ]);

        $package->refresh();

        return [
            'ok' => true,
            'detail' => $this->packageDetail($package),
            'message' => null,
        ];
    }
}
