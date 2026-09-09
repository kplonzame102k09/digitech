<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    /**
     * Display a listing of the current student's published grades.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $grades = Grade::query()
            ->where('studentId', $user->user_id)
            ->where('published', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'grades' => $grades->map(fn (Grade $grade): array => [
                'id' => $grade->id,
                'studentId' => $grade->studentId,
                'subject' => $grade->subject,
                'teacherId' => $grade->teacherId,
                'grade' => $grade->grade,
                'remarks' => $grade->remarks,
                'term' => $grade->term,
                'period' => $grade->period,
                'published' => $grade->published,
                'publishedAt' => $grade->publishedAt?->toIso8601String(),
                'publishedBy' => $grade->publishedBy,
                'notes' => $grade->notes,
                'updatedBy' => $grade->updatedBy,
                'createdAt' => $grade->created_at?->toIso8601String(),
                'updatedAt' => $grade->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Display the specified grade.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $grade = Grade::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->where('published', true)
            ->firstOrFail();

        return response()->json([
            'ok' => true,
            'grade' => [
                'id' => $grade->id,
                'studentId' => $grade->studentId,
                'subject' => $grade->subject,
                'teacherId' => $grade->teacherId,
                'grade' => $grade->grade,
                'remarks' => $grade->remarks,
                'term' => $grade->term,
                'period' => $grade->period,
                'published' => $grade->published,
                'publishedAt' => $grade->publishedAt?->toIso8601String(),
                'publishedBy' => $grade->publishedBy,
                'notes' => $grade->notes,
                'updatedBy' => $grade->updatedBy,
                'createdAt' => $grade->created_at?->toIso8601String(),
                'updatedAt' => $grade->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Students cannot create grades.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'Students cannot create grades.',
        ], 403);
    }

    /**
     * Students cannot update grades.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'Students cannot update grades.',
        ], 403);
    }

    /**
     * Students cannot delete grades.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'Students cannot delete grades.',
        ], 403);
    }
}
