<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = date('Y').'-'.(date('Y') + 1);

        $teacher = $this->seedUser([
            'user_id' => 'TCH-2026-000001',
            'role' => 'teacher',
            'firstName' => 'Maria',
            'lastName' => 'Santos',
            'email' => 'teacher@school.local',
            'username' => 'teacher',
            'password' => 'password',
            'employeeId' => 'EMP-0001',
            'department' => 'Senior High School',
            'birthDate' => '1985-04-12',
            'birthPlace' => 'Manila',
            'barangay' => 'Brgy. 1',
            'city' => 'Quezon City',
            'province' => 'NCR',
            'region' => 'NCR',
        ]);

        $students = [
            ['STU-2026-000001', 'Juan', 'Dela Cruz', 'juan.delacruz@student.local', 'student1', 'Grade 11', 'STEM', 'ACTIVE'],
            ['STU-2026-000002', 'Maria', 'Reyes', 'maria.reyes@student.local', 'student2', 'Grade 11', 'ABM', 'ACTIVE'],
            ['STU-2026-000003', 'Jose', 'Garcia', 'jose.garcia@student.local', 'student3', 'Grade 12', 'HUMSS', 'ACTIVE'],
        ];

        $studentModels = [];

        foreach ($students as [$id, $first, $last, $email, $username, $gradeLevel, $strand, $status]) {
            $studentModels[] = $this->seedUser([
                'user_id' => $id,
                'role' => 'student',
                'firstName' => $first,
                'lastName' => $last,
                'email' => $email,
                'username' => $username,
                'password' => 'password',
                'status' => strtolower($status),
                'strand' => $strand,
                'birthDate' => '2007-09-23',
                'birthPlace' => 'Quezon City',
                'barangay' => 'Brgy. 35',
                'city' => 'Quezon City',
                'province' => 'NCR',
                'region' => 'NCR',
                'contact' => '09171234567',
            ]);

            $this->seedEnrollment($id, $gradeLevel, $strand, $schoolYear, $teacher->user_id);
        }

        $parent = $this->seedUser([
            'user_id' => 'PAR-2026-000001',
            'role' => 'parent',
            'firstName' => 'Ana',
            'lastName' => 'Dela Cruz',
            'email' => 'parent@school.local',
            'username' => 'parent',
            'password' => 'password',
            'childId' => 'STU-2026-000001',
            'childIds' => ['STU-2026-000001'],
            'guardianName' => 'Ana Dela Cruz',
            'guardianContact' => '09171234567',
            'birthDate' => '1980-01-15',
            'birthPlace' => 'Manila',
        ]);

        $guest = $this->seedUser([
            'user_id' => 'GST-2026-000001',
            'role' => 'guest',
            'firstName' => 'Visitor',
            'lastName' => 'One',
            'email' => 'guest@school.local',
            'username' => 'guest',
            'password' => 'password',
            'birthDate' => '1990-05-20',
            'birthPlace' => 'Makati',
        ]);

        $studentModels = collect($studentModels);
        $this->linkParentChild($parent, $studentModels->first());

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'userId' => $studentModels->first()->user_id,
            'title' => 'Welcome to the portal',
            'message' => 'Your account has been created. You may now complete your enrollment profile.',
            'source' => 'system',
            'read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedUser(array $attributes): User
    {
        return User::updateOrCreate(
            ['user_id' => $attributes['user_id']],
            array_merge([
                'role' => 'student',
                'status' => 'active',
                'birthDate' => '2000-01-01',
                'birthPlace' => 'N/A',
                'barangay' => 'N/A',
                'city' => 'N/A',
                'province' => 'N/A',
                'region' => 'N/A',
                'contact' => '00000000000',
            ], $attributes, [
                'password' => Hash::make($attributes['password']),
            ]),
        );
    }

    private function seedEnrollment(string $studentId, string $gradeLevel, string $strand, string $schoolYear, ?string $assignedTeacherId = null): void
    {
        Enrollment::updateOrCreate(
            ['studentId' => $studentId, 'schoolYear' => $schoolYear],
            [
                'id' => (string) Str::uuid(),
                'status' => 'Enrolled',
                'programType' => 'Senior High School',
                'gradeLevel' => $gradeLevel,
                'strand' => $strand,
                'track' => $strand,
                'assignedSection' => $gradeLevel.' - '.$strand,
                'assignedTeacherId' => $assignedTeacherId,
            ],
        );
    }

    private function linkParentChild(?User $parent, ?User $child): void
    {
        if (! $parent || ! $child) {
            return;
        }

        DB::table('parent_student')->updateOrInsert(
            ['parent_id' => $parent->getKey(), 'student_id' => $child->getKey()],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
}
