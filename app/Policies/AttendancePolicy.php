<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttendancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher() || $user->isStudent() || $user->isParent();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            // Teachers can view attendance for their classrooms
            return $attendance->classroomId && 
                   $attendance->classroom->teacherId === $user->user_id;
        }

        if ($user->isStudent()) {
            // Students can view their own attendance
            return $attendance->studentId === $user->user_id;
        }

        if ($user->isParent()) {
            // Parents can view their children's attendance
            return $user->linkedChildren()->where('users.user_id', $attendance->studentId)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            // Teachers can update attendance for their classrooms if not locked
            if ($attendance->isLocked) {
                return false;
            }
            return $attendance->classroomId && 
                   $attendance->classroom->teacherId === $user->user_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can unlock the model.
     */
    public function unlock(User $user, Attendance $attendance): bool
    {
        // Only admins can unlock attendance records
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can authorize corrections.
     */
    public function authorizeCorrection(User $user, Attendance $attendance): bool
    {
        // Only admins can authorize corrections
        return $user->isAdmin();
    }
}
