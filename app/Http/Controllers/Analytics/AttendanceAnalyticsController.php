<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\AttendanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceAnalyticsController extends Controller
{
    public function __construct(private AttendanceAnalyticsService $analytics) {}

    /**
     * Server-computed attendance analytics scoped to the actor.
     * Non-breaking additive endpoint: JS falls back to local computation
     * when this is unreachable.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $overall = $this->analytics->overall($user);
        $trends = $this->analytics->trends($user);

        return response()->json([
            'ok' => true,
            'overall' => [
                'totalRecords' => $overall['total'],
                'overallRate' => $overall['rate'],
                'present' => $overall['presentLike'],
                'presentBreakdown' => [
                    'present' => $overall['present'],
                    'late' => $overall['late'],
                    'excused' => $overall['excused'],
                ],
                'absent' => $overall['absent'],
            ],
            'trends' => $trends,
            'byRecorder' => $this->analytics->byRecorder($user),
        ]);
    }
}
