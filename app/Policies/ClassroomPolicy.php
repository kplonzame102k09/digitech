<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTeacher() || $user->isAdmin() || $user->isStudent();
    }

    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return (string) $classroom->teacherId === $user->user_id;
        }

        if ($user->isStudent()) {
            // Only roster members may view a classroom (its roster, activities,
            // submissions and meeting rooms). "Any active classroom" browsing
            // leaks other learners' data to the whole student body.
            return $classroom->students()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && (string) $classroom->teacherId === $user->user_id);
    }

    public function manage(User $user, Classroom $classroom): bool
    {
        return $this->update($user, $classroom);
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && (string) $classroom->teacherId === $user->user_id);
    }
}
