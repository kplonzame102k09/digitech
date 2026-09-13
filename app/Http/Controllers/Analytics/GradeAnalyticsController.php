<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Services\PortalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeAnalyticsController extends Controller
{
    public function __construct(private PortalDataService $portal) {}

    /**
     * Server-computed grade summary scoped to the actor.
     *
     * Query params: studentId (optional; defaults per role), schoolYear, semester.
     * - student: own id only (any requested id is ignored).
     * - parent: must be a linked child, else 403.
     * - teacher: must be an enrolled/assigned student, else 403.
     * - admin: any student.
     *
     * Non-breaking: existing Student\GradeController@summary keeps working;
     * this is the shared single-source endpoint for teacher/admin/parent pages.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'studentId' => ['nullable', 'string', 'max:100'],
            'schoolYear' => ['nullable', 'string', 'max:30'],
            'semester' => ['nullable', 'string', 'max:30'],
        ]);

        $studentId = $this->resolveStudentId($user, $validated['studentId'] ?? null);
        abort_unless($studentId !== null, 403, 'Not authorized for this student.');

        $query = Grade::query()->where('studentId', $studentId);

        // Students/parents only see published grades; staff see all.
        if ($user->isStudent() || $user->isParent()) {
            $query->published();
        }

        if (! empty($validated['schoolYear'])) {
            $query->forSchoolYear($validated['schoolYear']);
        }

        if (! empty($validated['semester'])) {
            $query->forSemester($validated['semester']);
        }

        $grades = $query->orderBy('subject')->orderBy('semester')->get();
        $schoolYear = $validated['schoolYear'] ?? ($grades->first()?->schoolYear ?? '');

        // Published-only aggregates match Grade::gwaForStudent semantics.
        $published = $grades->filter(fn (Grade $g) => $g->published);
        $generalAverage = Grade::generalAverage($published);
        $annual = $schoolYear !== ''
            ? Grade::annualGwa($studentId, $schoolYear)
            : ['firstSemester' => ['generalAverage' => null, 'gwa' => null], 'secondSemester' => ['generalAverage' => null, 'gwa' => null], 'annualGeneralAverage' => null, 'annualGwa' => null];

        return response()->json([
            'ok' => true,
            'studentId' => $studentId,
            'schoolYear' => $schoolYear,
            'grades' => $grades->map(fn (Grade $g) => [
                'id' => $g->id,
                'studentId' => $g->studentId,
                'subject' => $g->subject,
                'semester' => $g->semester,
                'prelim' => $g->prelim !== null ? (float) $g->prelim : null,
                'midterm' => $g->midterm !== null ? (float) $g->midterm : null,
                'finals' => $g->finals !== null ? (float) $g->finals : null,
                'finalGrade' => $g->finalGrade !== null ? (float) $g->finalGrade : null,
                'units' => (float) $g->units,
                'schoolYear' => $g->schoolYear,
                'teacherId' => $g->teacherId,
                'remarks' => $g->remarks ?? $g->getRemark(),
                'published' => (bool) $g->published,
            ])->values(),
            'generalAverage' => $generalAverage,
            'gwa' => $generalAverage !== null ? Grade::convertToGwa($generalAverage) : null,
            'annual' => $annual,
        ]);
    }

    /**
     * Pure server-side final-grade preview (no DB write).
     * Replaces client-side zero-fill math with the authoritative
     * resampled-weight formula. JS keeps instant local preview as
     * fallback; server recalculation on persist remains authoritative.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prelim' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midterm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'finals' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $final = Grade::previewFinal(
            $validated['prelim'] ?? null,
            $validated['midterm'] ?? null,
            $validated['finals'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'finalGrade' => $final,
            'remarks' => $final === null ? null : ($final >= 75 ? 'Passed' : 'Failed'),
        ]);
    }

    private function resolveStudentId($user, ?string $requested): ?string
    {
        if ($user->isStudent()) {
            return $user->user_id;
        }

        if ($user->isParent()) {
            $linked = collect([$user->childId])
                ->concat($user->childIds ?? [])
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->all();

            $id = $requested ?? ($linked[0] ?? null);

            return $id !== null && in_array($id, $linked, true) ? $id : null;
        }

        if ($user->isTeacher()) {
            if ($requested === null) {
                return null;
            }

            $enrolled = $this->portal->getEnrolledStudentIds($user) ?? [];

            return in_array($requested, $enrolled, true) ? $requested : null;
        }

        // Admin (or others without restriction): requested id required.
        return $requested;
    }
}
