<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'classroomId' => ['nullable', 'string'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'status' => ['nullable', 'in:Present,Late,Absent,Excused'],
        ]);

        $query = Attendance::with(['classroom', 'student', 'recordedByUser'])
            ->orderByDesc('date');

        if ($request->has('classroomId')) {
            $query->where('classroomId', $request->input('classroomId'));
        }

        if ($request->has('startDate')) {
            $query->where('date', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('date', '<=', $request->input('endDate'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $attendanceRecords = $query->paginate(50);

        return response()->json([
            'ok' => true,
            'attendance' => $attendanceRecords->map(fn (Attendance $a) => [
                'id' => $a->id,
                'date' => $a->date,
                'student' => [
                    'id' => $a->student->user_id,
                    'name' => $a->student->firstName . ' ' . $a->student->lastName,
                ],
                'classroom' => $a->classroom ? [
                    'id' => $a->classroom->id,
                    'name' => $a->classroom->name,
                    'subject' => $a->classroom->subject,
                ] : null,
                'subject' => $a->subject,
                'session' => $a->session,
                'status' => $a->status,
                'timeIn' => $a->timeIn,
                'timeOut' => $a->timeOut,
                'remarks' => $a->remarks,
                'isLocked' => $a->isLocked,
                'isDraft' => $a->isDraft,
                'recordedBy' => $a->recordedByUser ? [
                    'id' => $a->recordedByUser->user_id,
                    'name' => $a->recordedByUser->firstName . ' ' . $a->recordedByUser->lastName,
                ] : null,
                'updatedAt' => $a->updated_at,
            ])->values(),
            'pagination' => [
                'total' => $attendanceRecords->total(),
                'per_page' => $attendanceRecords->perPage(),
                'current_page' => $attendanceRecords->currentPage(),
                'last_page' => $attendanceRecords->lastPage(),
            ],
        ]);
    }

    public function getLockedRecords(Request $request): JsonResponse
    {
        $query = Attendance::locked()
            ->with(['classroom', 'student', 'recordedByUser'])
            ->orderByDesc('date');

        if ($request->has('classroomId')) {
            $query->where('classroomId', $request->input('classroomId'));
        }

        if ($request->has('startDate')) {
            $query->where('date', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('date', '<=', $request->input('endDate'));
        }

        $lockedRecords = $query->get();

        return response()->json([
            'ok' => true,
            'lockedRecords' => $lockedRecords->map(fn (Attendance $a) => [
                'id' => $a->id,
                'date' => $a->date,
                'student' => [
                    'id' => $a->student->user_id,
                    'name' => $a->student->firstName . ' ' . $a->student->lastName,
                ],
                'classroom' => $a->classroom ? [
                    'id' => $a->classroom->id,
                    'name' => $a->classroom->name,
                    'subject' => $a->classroom->subject,
                ] : null,
                'status' => $a->status,
                'remarks' => $a->remarks,
                'recordedBy' => $a->recordedByUser ? [
                    'id' => $a->recordedByUser->user_id,
                    'name' => $a->recordedByUser->firstName . ' ' . $a->recordedByUser->lastName,
                ] : null,
                'updatedAt' => $a->updated_at,
            ])->values(),
        ]);
    }

    public function unlockRecord(Request $request, string $recordId): JsonResponse
    {
        $attendance = Attendance::findOrFail($recordId);
        
        if (!$attendance->isLocked) {
            return response()->json([
                'ok' => false,
                'error' => 'Record is not locked',
            ], 422);
        }

        $admin = $request->user();
        
        // Authorize the unlock action
        $this->authorize('update', $attendance);

        $attendance->update(['isLocked' => false]);

        AuditLog::record([
            'entity' => 'attendance',
            'recordId' => $attendance->id,
            'action' => 'attendance.unlocked',
            'actorId' => $admin->user_id,
            'notes' => "Attendance record unlocked by admin for correction",
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Attendance record unlocked successfully',
        ]);
    }

    public function authorizeCorrection(Request $request, string $recordId): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $attendance = Attendance::findOrFail($recordId);
        
        if (!$attendance->isLocked) {
            return response()->json([
                'ok' => false,
                'error' => 'Record is not locked',
            ], 422);
        }

        $admin = $request->user();
        
        // Authorize the correction action
        $this->authorize('update', $attendance);

        // Unlock the record
        $attendance->update(['isLocked' => false]);

        AuditLog::record([
            'entity' => 'attendance',
            'recordId' => $attendance->id,
            'action' => 'attendance.correction_authorized',
            'actorId' => $admin->user_id,
            'notes' => "Attendance correction authorized by admin. Reason: {$request->input('reason')}",
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Attendance correction authorized successfully',
        ]);
    }

    public function getCorrectionRequests(Request $request): JsonResponse
    {
        // Get audit logs for attendance corrections
        $correctionLogs = AuditLog::where('entity', 'attendance')
            ->where('action', 'attendance.correction_authorized')
            ->with(['actor'])
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'ok' => true,
            'corrections' => $correctionLogs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'recordId' => $log->recordId,
                'authorizedBy' => $log->actor ? [
                    'id' => $log->actor->user_id,
                    'name' => $log->actor->firstName . ' ' . $log->actor->lastName,
                ] : null,
                'reason' => $log->notes,
                'date' => $log->date,
            ])->values(),
        ]);
    }

    public function getMonitoringStats(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $query = Attendance::query();

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $totalRecords = $query->count();
        $lockedRecords = (clone $query)->locked()->count();
        $draftRecords = (clone $query)->draft()->count();

        $byStatus = [
            'present' => (clone $query)->where('status', Attendance::PRESENT)->count(),
            'late' => (clone $query)->where('status', Attendance::LATE)->count(),
            'absent' => (clone $query)->where('status', Attendance::ABSENT)->count(),
            'excused' => (clone $query)->where('status', Attendance::EXCUSED)->count(),
        ];

        return response()->json([
            'ok' => true,
            'stats' => [
                'totalRecords' => $totalRecords,
                'lockedRecords' => $lockedRecords,
                'draftRecords' => $draftRecords,
                'byStatus' => $byStatus,
            ],
        ]);
    }
}
