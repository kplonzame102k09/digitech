<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\AttendanceReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceReviewController extends Controller
{
    public function __construct(private AttendanceReviewService $reviews) {}

    /**
     * The adviser's review workspace for one date: every advisee with the
     * per-classroom marks behind them and the current whole-day final.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'ok' => true,
            ...$this->reviews->reviewFor($request->user(), $validated['date']),
        ]);
    }

    /**
     * VERIFY / EDIT: set (or replace) one advisee's official status for the day.
     */
    public function edit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'studentId' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:Present,Late,Absent,Excused'],
        ]);

        $result = $this->reviews->setFinal(
            $request->user(),
            $validated['date'],
            $validated['studentId'],
            $validated['status'],
        );

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'error' => $result['message'],
                'blocked' => true,
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'students' => $result['student'],
        ]);
    }

    /**
     * SUBMIT TO ADMIN: freeze edits, fill any missing finals, and create the package.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'date_format:Y-m-d'],
        ]);

        $result = $this->reviews->submit($request->user(), $validated['date']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['message']], 422);
        }

        return response()->json([
            'ok' => true,
            'package' => $result['package'],
            ...$this->reviews->reviewFor($request->user(), $validated['date']),
        ]);
    }
}
