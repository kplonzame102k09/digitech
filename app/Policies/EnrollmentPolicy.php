<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStudent();
    }

    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdmin()
            || ($user->isStudent() && (string) $enrollment->studentId === $user->user_id);
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdmin()
            || ($user->isStudent() && (string) $enrollment->studentId === $user->user_id);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->isStudent() && (string) $enrollment->studentId === $user->user_id;
    }
}
