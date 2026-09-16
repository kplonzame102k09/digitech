<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Server-side attendance analytics (Phase 1).
 *
 * Single source of truth for attendance rates. Client-side JS in
 * public/js/workflows.js keeps a local fallback renderer so pages work
 * when this endpoint is unreachable, but this service is authoritative
 * whenever the API is available.
 *
 * Rate definition: (Present + Late + Excused) / Total.
 *
 * Counts only the official record — admin-finalized finals
 * (Attendance::scopeFinalized). Submitted-but-pending packages and
 * provisional classroom checks never appear here.
 */
class AttendanceAnalyticsService
{
    public const PRESENT_LIKE = ['Present', 'Late', 'Excused'];

    public function __construct(private PortalDataService $portal) {}

    /**
     * Student ids visible to the actor for attendance rows.
     * null means unrestricted (admin).
     *
     * @return array<int, string>|null
     */
    public function visibleStudentIds(?User $actor): ?array
    {
        if (! $actor || $actor->isAdmin()) {
            return null;
        }

        if ($actor->isStudent() || $actor->isGuest()) {
            return [$actor->user_id];
        }

        if ($actor->isTeacher()) {
            return $this->portal->getEnrolledStudentIds($actor) ?? [];
        }

        if ($actor->isParent()) {
            $ids = collect([$actor->childId])
                ->concat($actor->childIds ?? [])
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->all();

            $pivotIds = DB::table('parent_student')
                ->join('users as linked', 'linked.id', '=', 'parent_student.student_id')
                ->where('parent_student.parent_id', $actor->getKey())
                ->pluck('linked.user_id')
                ->all();

            return array_values(array_unique(array_merge($ids, $pivotIds)));
        }

        return null;
    }

    /**
     * Base attendance query scoped to what the actor may see, restricted to
     * the official record: only admin-finalized finals (kind='final' with a
     * finalizedAt stamp) count in analytics. Pending submissions and
     * provisional classroom marks never count.
     */
    public function scopedQuery(?User $actor)
    {
        $query = Attendance::query()->finalized();
        $visible = $this->visibleStudentIds($actor);

        if ($visible !== null) {
            $query->whereIn('studentId', $visible === [] ? ['__none__'] : $visible);
        }

        return $query;
    }

    /**
     * Overall counters + rate.
     *
     * @return array{total:int,present:int,late:int,excused:int,absent:int,presentLike:int,rate:float}
     */
    public function overall(?User $actor): array
    {
        $rows = $this->scopedQuery($actor)->get(['status']);

        $present = $rows->where('status', 'Present')->count();
        $late = $rows->where('status', 'Late')->count();
        $excused = $rows->where('status', 'Excused')->count();
        $absent = $rows->where('status', 'Absent')->count();
        $total = $rows->count();
        $presentLike = $present + $late + $excused;

        return [
            'total' => $total,
            'present' => $present,
            'late' => $late,
            'excused' => $excused,
            'absent' => $absent,
            'presentLike' => $presentLike,
            'rate' => $total > 0 ? round($presentLike / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Per-date attendance vs absent rates, most recent 30 valid YYYY-MM-DD days.
     *
     * @return array{dates:array<int,string>,labels:array<int,string>,attendanceRate:array<int,float>,absentRate:array<int,float>}
     */
    public function trends(?User $actor, int $limit = 30): array
    {
        $rows = $this->scopedQuery($actor)->get(['date', 'status']);

        $groups = [];
        foreach ($rows as $row) {
            $date = $row->date instanceof \DateTimeInterface
                ? $row->date->format('Y-m-d')
                : (string) ($row->getRawOriginal('date') ?? $row->date ?? '');

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $groups[$date] ??= ['total' => 0, 'present' => 0, 'absent' => 0];
            $groups[$date]['total']++;

            if (in_array($row->status, self::PRESENT_LIKE, true)) {
                $groups[$date]['present']++;
            } elseif ($row->status === 'Absent') {
                $groups[$date]['absent']++;
            }
        }

        ksort($groups);
        $dates = array_slice(array_keys($groups), -$limit);

        $attendanceRate = [];
        $absentRate = [];
        $labels = [];

        foreach ($dates as $date) {
            $g = $groups[$date];
            $attendanceRate[] = $g['total'] > 0 ? round($g['present'] / $g['total'] * 100, 1) : 0.0;
            $absentRate[] = $g['total'] > 0 ? round($g['absent'] / $g['total'] * 100, 1) : 0.0;
            [$y, $m, $d] = array_map('intval', explode('-', $date));
            $labels[] = date('M j', mktime(0, 0, 0, $m, $d, $y));
        }

        return [
            'dates' => $dates,
            'labels' => $labels,
            'attendanceRate' => $attendanceRate,
            'absentRate' => $absentRate,
        ];
    }

    /**
     * Per-recorder stats (teachers + admins who recorded attendance).
     * Only meaningful for admins; returns [] for other roles to avoid leaking.
     */
    public function byRecorder(User $actor): array
    {
        if (! $actor->isAdmin()) {
            return [];
        }

        $rows = Attendance::query()->finalized()->get(['recordedBy', 'status']);

        $recorders = User::query()
            ->whereIn('role', ['teacher', 'admin'])
            ->get(['user_id', 'firstName', 'lastName', 'role', 'photo']);

        return $recorders
            ->map(function (User $recorder) use ($rows) {
                $mine = $rows->where('recordedBy', $recorder->user_id);
                $total = $mine->count();
                $present = $mine->where('status', 'Present')->count();
                $late = $mine->where('status', 'Late')->count();
                $excused = $mine->where('status', 'Excused')->count();
                $absent = $mine->where('status', 'Absent')->count();
                $presentLike = $present + $late + $excused;

                return [
                    'id' => $recorder->user_id,
                    'name' => trim($recorder->firstName.' '.$recorder->lastName) ?: $recorder->user_id,
                    'role' => $recorder->role,
                    'photo' => $recorder->photo,
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'excused' => $excused,
                    'absent' => $absent,
                    'rate' => $total > 0 ? round($presentLike / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Per-student finalized attendance summarized from every finalized row
     * a given recorder entered. Only meaningful for admins.
     *
     * @return array<int, array{id:string,name:string,photo:string|null,total:int,present:int,late:int,excused:int,absent:int,rate:float}>
     */
    public function byRecorderStudents(string $recorderId): array
    {
        $rows = Attendance::query()
            ->finalized()
            ->where('recordedBy', $recorderId)
            ->get(['studentId', 'status']);

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('studentId')->unique()->values()->all();

        $students = User::query()
            ->where('role', 'student')
            ->whereIn('user_id', $ids)
            ->get(['user_id', 'firstName', 'lastName', 'photo']);

        return $students
            ->map(function (User $student) use ($rows) {
                $mine = $rows->where('studentId', $student->user_id);
                $total = $mine->count();
                $present = $mine->where('status', 'Present')->count();
                $late = $mine->where('status', 'Late')->count();
                $excused = $mine->where('status', 'Excused')->count();
                $absent = $mine->where('status', 'Absent')->count();
                $presentLike = $present + $late + $excused;

                return [
                    'id' => $student->user_id,
                    'name' => trim($student->firstName.' '.$student->lastName) ?: $student->user_id,
                    'photo' => $student->photo,
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'excused' => $excused,
                    'absent' => $absent,
                    'rate' => $total > 0 ? round($presentLike / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
