<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = $user->role === 'parent' ? $this->getStudentId($request) : $user->user_id;

        if (!$studentId) {
            return response()->json(['ok' => false, 'error' => 'Student not found'], 404);
        }

        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $query = Attendance::where('studentId', $studentId)
            ->with(['classroom', 'student'])
            ->finalized()
            ->orderByDesc('date');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $attendanceRecords = $query->get();

        return response()->json([
            'ok' => true,
            'attendance' => $attendanceRecords->map(fn (Attendance $a) => [
                'id' => $a->id,
                'date' => $a->date,
                'subject' => $a->subject,
                'classroom' => $a->classroom ? [
                    'id' => $a->classroom->id,
                    'name' => $a->classroom->name,
                ] : null,
                'session' => $a->session,
                'status' => $a->status,
                'timeIn' => $a->timeIn,
                'timeOut' => $a->timeOut,
                'remarks' => $a->remarks,
                'isLocked' => $a->isLocked,
                'recordedBy' => $a->recordedBy,
            ])->values(),
        ]);
    }

    public function getClassroomAttendance(Request $request, string $classroomId): JsonResponse
    {
        $user = $request->user();
        $studentId = $user->role === 'parent' ? $this->getStudentId($request) : $user->user_id;

        if (!$studentId) {
            return response()->json(['ok' => false, 'error' => 'Student not found'], 404);
        }

        $classroom = Classroom::query()->findOrFail($classroomId);

        // Verify student is enrolled in this classroom
        $isEnrolled = $classroom->students()->where('user_id', $studentId)->exists();
        if (!$isEnrolled) {
            return response()->json(['ok' => false, 'error' => 'Student not enrolled in this classroom'], 403);
        }

        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $query = Attendance::forClassroom($classroomId)
            ->where('studentId', $studentId)
            ->finalized()
            ->orderByDesc('date');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $attendanceRecords = $query->get();

        return response()->json([
            'ok' => true,
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'subject' => $classroom->subject,
            ],
            'attendance' => $attendanceRecords->map(fn (Attendance $a) => [
                'id' => $a->id,
                'date' => $a->date,
                'session' => $a->session,
                'status' => $a->status,
                'timeIn' => $a->timeIn,
                'timeOut' => $a->timeOut,
                'remarks' => $a->remarks,
                'isLocked' => $a->isLocked,
            ])->values(),
        ]);
    }

    public function getSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = $user->role === 'parent' ? $this->getStudentId($request) : $user->user_id;

        if (!$studentId) {
            return response()->json(['ok' => false, 'error' => 'Student not found'], 404);
        }

        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $query = Attendance::where('studentId', $studentId)->finalized();

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $records = $query->get();

        $summary = [
            'present' => $records->where('status', Attendance::PRESENT)->count(),
            'late' => $records->where('status', Attendance::LATE)->count(),
            'absent' => $records->where('status', Attendance::ABSENT)->count(),
            'excused' => $records->where('status', Attendance::EXCUSED)->count(),
            'total' => $records->count(),
            'attendanceRate' => Attendance::getAttendanceRate($studentId, $startDate, $endDate),
        ];

        return response()->json([
            'ok' => true,
            'summary' => $summary,
        ]);
    }

    private function getStudentId(Request $request): ?string
    {
        $user = $request->user();
        
        if ($user->role !== 'parent') {
            return $user->user_id;
        }

        // For parents, get student ID from request or use linked child
        $studentId = $request->input('studentId');
        
        if ($studentId) {
            // Verify parent has access to this student
            $hasAccess = User::where('user_id', $studentId)
                ->whereHas('parentLinks', function ($query) use ($user) {
                    $query->where('users.user_id', $user->user_id);
                })->exists();
            
            return $hasAccess ? $studentId : null;
        }

        // Get first linked child if no specific student requested
        $child = User::whereHas('parentLinks', function ($query) use ($user) {
            $query->where('users.user_id', $user->user_id);
        })->first();

        return $child?->user_id;
    }
}
