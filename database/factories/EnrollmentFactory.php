<?php

namespace Database\Factories;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'studentId' => fn () => fake()->unique()->numerify('STU-2026-######'),
            'status' => 'Draft',
            'programType' => 'Senior High',
            'gradeLevel' => 'Grade 12',
            'strand' => 'ICT',
            'track' => 'CSS NCII',
            'schoolYear' => '2026-2027',
            'trainingLevel' => null,
            'assignedTeacherId' => null,
            'assignedSection' => null,
            'reviewNotes' => null,
            'rejectionReason' => null,
            'reviewedAt' => null,
            'reviewedBy' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => 'Submitted']);
    }

    public function enrolled(): static
    {
        return $this->state([
            'status' => 'Enrolled',
            'reviewedAt' => now()->toIso8601String(),
            'reviewedBy' => 'ADMIN-000001',
        ]);
    }
}
