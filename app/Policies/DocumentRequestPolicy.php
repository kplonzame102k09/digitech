<?php

namespace App\Policies;

use App\Models\DocumentRequest;
use App\Models\User;

class DocumentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStudent() || $user->isGuest();
    }

    public function create(User $user): bool
    {
        return $user->isStudent() || $user->isGuest();
    }

    public function view(User $user, DocumentRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $who = $request->createdBy ?? $request->studentId;

        return ($user->isStudent() || $user->isGuest()) && (string) $who === $user->user_id;
    }

    public function update(User $user, DocumentRequest $request): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, DocumentRequest $request): bool
    {
        $who = $request->createdBy ?? $request->studentId;

        return ($user->isStudent() || $user->isGuest()) && (string) $who === $user->user_id;
    }
}
