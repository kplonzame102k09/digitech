<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        $prelim = fake()->randomFloat(2, 60, 99);
        $midterm = fake()->randomFloat(2, 60, 99);
        $finals = fake()->randomFloat(2, 60, 99);

        return [
            'id' => (string) Str::orderedUuid(),
            'studentId' => User::factory(),
            'subject' => fake()->randomElement(['Mathematics', 'English', 'Science', 'Filipino', 'ICT', 'PE']),
            'semester' => Grade::SEMESTER_FIRST,
            'prelim' => $prelim,
            'midterm' => $midterm,
            'finals' => $finals,
            'finalGrade' => round(
                ($prelim * Grade::PRELIM_WEIGHT) + ($midterm * Grade::MIDTERM_WEIGHT) + ($finals * Grade::FINALS_WEIGHT),
                2
            ),
            'units' => 1.00,
            'schoolYear' => '2026-2027',
            'teacherId' => null,
            'remarks' => null,
            'published' => false,
            'publishedAt' => null,
            'publishedBy' => null,
            'notes' => null,
            'updatedBy' => null,
        ];
    }

    public function secondSemester(): static
    {
        return $this->state(['semester' => Grade::SEMESTER_SECOND]);
    }

    public function published(): static
    {
        return $this->state([
            'published' => true,
            'publishedAt' => now(),
            'publishedBy' => 'ADMIN-000001',
        ]);
    }

    public function inSchoolYear(string $schoolYear): static
    {
        return $this->state(['schoolYear' => $schoolYear]);
    }

    public function subject(string $subject): static
    {
        return $this->state(['subject' => $subject]);
    }

    public function units(float $units): static
    {
        return $this->state(['units' => $units]);
    }
}
