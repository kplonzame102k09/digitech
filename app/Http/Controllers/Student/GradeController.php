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

        $query = Grade::query()
            ->where('studentId', $user->user_id)
            ->where('published', true)
            ->orderBy('subject')
            ->orderBy('semester');

        if ($request->filled('semester')) {
            $query->where('semester', $request->string('semester'));
        }

        $grades = $query->get();

        return response()->json([
            'ok' => true,
            'grades' => $grades->map(fn (Grade $grade): array => $this->serialize($grade))->values(),
        ]);
    }

    /**
     * Return the authenticated student's grade summary with averages and GWA.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $grades = Grade::query()
            ->where('studentId', $user->user_id)
            ->where('published', true)
            ->orderBy('subject')
            ->orderBy('semester')
            ->get();

        $schoolYear = $request->string('schoolYear')->toString() ?: ($grades->first()?->schoolYear ?? '');

        $summary = Grade::gwaForStudent($user->user_id, $schoolYear ?: null);
        $annual = Grade::annualGwa($user->user_id, $schoolYear ?: '');

        return response()->json([
            'ok' => true,
            'grades' => $grades->map(fn (Grade $grade): array => $this->serialize($grade))->values(),
            'schoolYear' => $schoolYear,
            'generalAverage' => $summary['generalAverage'],
            'gwa' => $summary['gwa'],
            'totalUnits' => $summary['totalUnits'],
            'annual' => $annual,
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
            'grade' => $this->serialize($grade),
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

    private function serialize(Grade $grade): array
    {
        return [
            'id' => $grade->id,
            'studentId' => $grade->studentId,
            'subject' => $grade->subject,
            'semester' => $grade->semester,
            'prelim' => $grade->prelim !== null ? (float) $grade->prelim : null,
            'midterm' => $grade->midterm !== null ? (float) $grade->midterm : null,
            'finals' => $grade->finals !== null ? (float) $grade->finals : null,
            'finalGrade' => $grade->finalGrade !== null ? (float) $grade->finalGrade : null,
            'units' => (float) $grade->units,
            'schoolYear' => $grade->schoolYear,
            'teacherId' => $grade->teacherId,
            'remarks' => $grade->remarks,
            'published' => (bool) $grade->published,
            'publishedAt' => $grade->publishedAt?->toIso8601String(),
            'publishedBy' => $grade->publishedBy,
            'notes' => $grade->notes,
            'updatedBy' => $grade->updatedBy,
            'createdAt' => $grade->created_at?->toIso8601String(),
            'updatedAt' => $grade->updated_at?->toIso8601String(),
        ];
    }
}
