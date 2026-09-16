<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePackage;
use App\Services\AttendanceReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceFinalizeController extends Controller
{
    public function __construct(private AttendanceReviewService $reviews) {}

    /**
     * Every submission awaiting (or already) finalization, newest first.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'packages' => $this->reviews->adminPackages(),
        ]);
    }

    /**
     * Open one package: the adviser's submission with each student's final and
     * the classroom marks behind it.
     */
    public function show(string $id): JsonResponse
    {
        $package = AttendancePackage::query()->findOrFail($id);

        return response()->json([
            'ok' => true,
            ...$this->reviews->packageDetail($package),
        ]);
    }

    /**
     * FINALIZE: lock the package and stamp every advisee's final row.
     */
    public function finalize(Request $request, string $id): JsonResponse
    {
        $package = AttendancePackage::query()->findOrFail($id);

        $result = $this->reviews->finalize($request->user(), $package);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['message']], 422);
        }

        return response()->json(['ok' => true, ...$result['detail']]);
    }

    /**
     * RETURN: send a submitted package back to its adviser for correction.
     * The adviser re-edits the finals and resubmits the day.
     */
    public function returnPackage(Request $request, string $id): JsonResponse
    {
        $package = AttendancePackage::query()->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->reviews->returnPackage($request->user(), $package, trim($validated['reason']));

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['message']], 422);
        }

        return response()->json(['ok' => true, ...$result['detail']]);
    }
}
