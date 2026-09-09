<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Grade extends Model
{
    use HasFactory;

    public const SEMESTER_FIRST = '1st Semester';

    public const SEMESTER_SECOND = '2nd Semester';

    public const TERM_PRELIM = 'Prelim';

    public const TERM_MIDTERM = 'Midterm';

    public const TERM_FINALS = 'Finals';

    public const PRELIM_WEIGHT = 0.20;

    public const MIDTERM_WEIGHT = 0.30;

    public const FINALS_WEIGHT = 0.50;

    protected $fillable = [
        'id',
        'studentId',
        'subject',
        'semester',
        'prelim',
        'midterm',
        'finals',
        'finalGrade',
        'units',
        'schoolYear',
        'teacherId',
        'remarks',
        'published',
        'publishedAt',
        'publishedBy',
        'notes',
        'updatedBy',
    ];

    protected $casts = [
        'prelim' => 'float',
        'midterm' => 'float',
        'finals' => 'float',
        'finalGrade' => 'float',
        'units' => 'float',
        'semester' => 'string',
        'schoolYear' => 'string',
        'published' => 'boolean',
        'publishedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }

    public function teacherUser()
    {
        return $this->belongsTo(User::class, 'teacherId', 'user_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publishedBy', 'user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updatedBy', 'user_id');
    }

    public function recalculateFinalGrade(): ?float
    {
        $grades = collect([$this->prelim, $this->midterm, $this->finals])
            ->map(fn ($value) => $value !== null && $value !== '' ? (float) $value : null);

        if ($grades->every(fn ($value) => $value === null)) {
            $this->finalGrade = null;

            return null;
        }

        $this->finalGrade = round(
            (float) $grades[0] * self::PRELIM_WEIGHT +
            (float) $grades[1] * self::MIDTERM_WEIGHT +
            (float) $grades[2] * self::FINALS_WEIGHT,
            2
        );

        return $this->finalGrade;
    }

    public function scopeForSchoolYear(Builder $query, string $schoolYear): Builder
    {
        return $query->where('schoolYear', $schoolYear);
    }

    public function scopeForSemester(Builder $query, string $semester): Builder
    {
        return $query->where('semester', $semester);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public static function convertToGwa(float $average): float
    {
        return match (true) {
            $average >= 97 => 1.00,
            $average >= 94 => 1.25,
            $average >= 91 => 1.50,
            $average >= 88 => 1.75,
            $average >= 85 => 2.00,
            $average >= 82 => 2.25,
            $average >= 79 => 2.50,
            $average >= 76 => 2.75,
            $average >= 75 => 3.00,
            default => 5.00,
        };
    }

    public static function generalAverage(Collection $grades): ?float
    {
        $eligible = $grades
            ->filter(fn (Grade $grade) => $grade->finalGrade !== null && $grade->units > 0);

        if ($eligible->isEmpty()) {
            return null;
        }

        $totalUnits = $eligible->sum('units');
        $weightedSum = $eligible->sum(fn (Grade $grade) => $grade->finalGrade * $grade->units);

        return round($weightedSum / $totalUnits, 2);
    }

    /**
     * Compute the General Average and GWA for a student.
     *
     * @return array{generalAverage: ?float, gwa: ?float, totalUnits: float}
     */
    public static function gwaForStudent(
        string $studentId,
        ?string $schoolYear = null,
        ?string $semester = null
    ): array {
        $query = self::query()->where('studentId', $studentId)->published();

        if ($schoolYear !== null) {
            $query->forSchoolYear($schoolYear);
        }

        if ($semester !== null) {
            $query->forSemester($semester);
        }

        $grades = $query->get();

        $generalAverage = self::generalAverage($grades);

        return [
            'generalAverage' => $generalAverage,
            'gwa' => $generalAverage !== null ? self::convertToGwa($generalAverage) : null,
            'totalUnits' => round($grades->filter(fn (Grade $grade) => $grade->finalGrade !== null)->sum('units'), 2),
        ];
    }

    /**
     * Compute per-semester and annual GWA for a student in a school year.
     *
     * @return array{
     *     firstSemester: array{generalAverage: ?float, gwa: ?float},
     *     secondSemester: array{generalAverage: ?float, gwa: ?float},
     *     annualGeneralAverage: ?float,
     *     annualGwa: ?float
     * }
     */
    public static function annualGwa(string $studentId, string $schoolYear): array
    {
        $first = self::gwaForStudent($studentId, $schoolYear, self::SEMESTER_FIRST);
        $second = self::gwaForStudent($studentId, $schoolYear, self::SEMESTER_SECOND);

        $averages = array_filter([$first['generalAverage'], $second['generalAverage']], fn ($value) => $value !== null);

        $annualAverage = $averages !== [] ? round(array_sum($averages) / count($averages), 2) : null;

        return [
            'firstSemester' => [
                'generalAverage' => $first['generalAverage'],
                'gwa' => $first['gwa'],
            ],
            'secondSemester' => [
                'generalAverage' => $second['generalAverage'],
                'gwa' => $second['gwa'],
            ],
            'annualGeneralAverage' => $annualAverage,
            'annualGwa' => $annualAverage !== null ? self::convertToGwa($annualAverage) : null,
        ];
    }

    public function getNumericGrade(): ?float
    {
        if ($this->finalGrade === null || $this->finalGrade === '') {
            return null;
        }

        return (float) $this->finalGrade;
    }

    public function getRemark(): string
    {
        if ($this->remarks) {
            return $this->remarks;
        }

        $numericGrade = $this->getNumericGrade();

        return ($numericGrade !== null && $numericGrade >= 75) ? 'Passed' : 'Failed';
    }

    public function isPublished(): bool
    {
        return $this->published === true || $this->published === 'true' || $this->status === 'Published';
    }
}
