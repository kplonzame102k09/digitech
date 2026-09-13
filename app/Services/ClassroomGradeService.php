<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomSubmission;
use App\Models\Grade;

/**
 * Classroom grading rule (Phase: per-activity -> term average -> final mean -> GWA).
 *
 * - Each activity is tagged Prelim|Midterm|Finals.
 * - Per student per term: mean of SCORED submissions in that term's
 *   activities (unscored/unsubmitted excluded, never zero-filled).
 * - Final: simple mean of the PRESENT term averages (null if none).
 * - GWA: existing CHED scale via Grade::convertToGwa (unchanged).
 *
 * NOTE: intentionally different from Grade::previewFinal (resampled
 * 20/30/50 weights) which remains authoritative for non-classroom grades.
 */
class ClassroomGradeService
{
    public const TERMS = ['Prelim', 'Midterm', 'Finals'];

    public static function normalizeTerm(?string $term): string
    {
        $term = trim((string) $term);

        foreach (self::TERMS as $valid) {
            if (strcasecmp($term, $valid) === 0 || strcasecmp($term, $valid.'s') === 0) {
                return $valid;
            }
        }

        return 'Prelim';
    }

    /**
     * @return array{averages:array<string,float|null>,counts:array<string,int>,final:?float,gwa:?float}
     */
    public function forStudent(Classroom $classroom, string $studentId): array
    {
        $averages = [];
        $counts = [];

        foreach (self::TERMS as $term) {
            $scores = ClassroomSubmission::query()
                ->join('classroom_activities', 'classroom_activities.id', '=', 'classroom_submissions.activity_id')
                ->where('classroom_activities.classroom_id', $classroom->id)
                ->where('classroom_activities.term', $term)
                ->where('classroom_submissions.studentId', $studentId)
                ->whereNotNull('classroom_submissions.score')
                ->pluck('classroom_submissions.score');

            $counts[$term] = $scores->count();
            $averages[$term] = $scores->isNotEmpty() ? round($scores->avg(), 2) : null;
        }

        $present = array_values(array_filter($averages, fn ($v) => $v !== null));
        $final = $present !== [] ? round(array_sum($present) / count($present), 2) : null;

        return [
            'averages' => $averages,
            'counts' => $counts,
            'final' => $final,
            'gwa' => $final !== null ? Grade::convertToGwa($final) : null,
        ];
    }

    public static function meanOfPresent(?float ...$terms): ?float
    {
        $present = array_values(array_filter($terms, fn ($v) => $v !== null));
        if ($present === []) {
            return null;
        }

        return round(array_sum($present) / count($present), 2);
    }
}
