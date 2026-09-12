<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;
use App\Services\PortalDataService;

class GradePolicy
{
    public function __construct(private PortalDataService $portal) {}

    public function viewAny(User $user): bool
    {
        return $user->isStudent() || $user->isParent();
    }

    public function view(User $user, Grade $grade): bool
    {
        if ($user->isAdmin() || $user->isStudent() && (string) $grade->studentId === $user->user_id) {
            return true;
        }

        if ($user->isParent()) {
            $childIds = collect([$user->childId])
                ->concat($user->childIds ?? [])
                ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
                ->all();

            return in_array($grade->studentId, $childIds, true);
        }

        return false;
    }

    public function viewTeacherRecord(User $user, Grade $grade): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && (string) $grade->teacherId === $user->user_id)
            || ($user->isTeacher() && in_array((string) $grade->studentId, $this->portal->getEnrolledStudentIds($user) ?? [], true));
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Grade $grade): bool
    {
        return $user->isAdmin() || $user->isTeacher() && (string) $grade->teacherId === $user->user_id;
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $user->isAdmin() || $user->isTeacher() && (string) $grade->teacherId === $user->user_id;
    }
}
