<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

/**
 * Shared ownership checks for classroom activities/submissions/files.
 */
class ClassroomWorkPolicy
{
    public function ownsClassroom(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && (string) $classroom->teacherId === $user->user_id);
    }

    public function isRosterStudent(User $user, Classroom $classroom): bool
    {
        if (! $user->isStudent()) {
            return false;
        }

        return $classroom->students()->where('users.id', $user->id)->exists();
    }

    public function canViewClassroom(User $user, Classroom $classroom): bool
    {
        return $this->ownsClassroom($user, $classroom)
            || $this->isRosterStudent($user, $classroom);
    }
}
