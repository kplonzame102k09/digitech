<?php

namespace App\Services;

use App\Models\PortalCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PortalDataService
{
    public const COLLECTION_KEYS = [
        'enrollments',
        'documentRequests',
        'grades',
        'competencies',
        'notifications',
        'announcements',
        'attendance',
        'auditLogs',
        'requirements',
        'parentLinkRequests',
        'settings',
    ];

    public function bootPayload(?User $authUser = null): array
    {
        return [
            'csrf' => csrf_token(),
            'logoutUrl' => url('/logout'),
            'apiBase' => url('/api/portal'),
            'currentUser' => $authUser ? $this->userToJs($authUser) : null,
            'collections' => $this->allCollections(),
        ];
    }

    public function allCollections(): array
    {
        $data = ['users' => $this->usersForJs()];

        foreach (self::COLLECTION_KEYS as $key) {
            $data[$key] = $this->getCollection($key);
        }

        return $data;
    }

    public function getCollection(string $key): mixed
    {
        if ($key === 'users') {
            return $this->usersForJs();
        }

        if ($key === 'currentUser') {
            return null;
        }

        abort_unless(in_array($key, self::COLLECTION_KEYS, true), 404, 'Unknown collection');

        $value = PortalCollection::query()->find($key)?->value;

        return $key === 'settings' ? $value ?? $this->defaultSettings() : $value ?? [];
    }

    public function putCollection(string $key, mixed $value, ?User $actor = null): mixed
    {
        if ($key === 'users') {
            return $this->syncUsers(is_array($value) ? $value : [], $actor);
        }

        abort_unless(in_array($key, self::COLLECTION_KEYS, true), 404, 'Unknown collection');

        $value = $key === 'settings'
            ? (is_array($value) ? $value : $this->defaultSettings())
            : (is_array($value) ? array_values($value) : []);

        PortalCollection::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        return $value;
    }

    public function usersForJs(): array
    {
        return User::query()
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->userToJs($user))
            ->values()
            ->all();
    }

    public function userToJs(User $user): array
    {
        return array_merge(is_array($user->profile_extra) ? $user->profile_extra : [], [
            'id' => $user->user_id,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'middleName' => $user->middleName,
            'email' => $user->email,
            'username' => $user->username,
            'contact' => $user->contact,
            'role' => $user->role,
            'status' => $user->status ?: 'active',
            'birthDate' => $user->birthDate?->format('Y-m-d'),
            'birthPlace' => $user->birthPlace,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'region' => $user->region,
            'photo' => $user->photo ?: '',
            'strand' => $user->strand,
            'address' => $user->address,
            'childId' => $user->childId,
            'childIds' => $user->childIds ?? [],
            'guardianName' => $user->guardianName,
            'guardianContact' => $user->guardianContact,
            'mustChangePassword' => (bool) $user->mustChangePassword,
            'employeeId' => $user->employeeId,
            'department' => $user->department,
            'createdAt' => $user->created_at?->toIso8601String(),
            'updatedAt' => $user->updated_at?->toIso8601String(),
            'password' => '',
        ]);
    }

    protected function syncUsers(array $users, ?User $actor): array
    {
        if (! $actor?->isAdmin()) {
            return $this->syncOwnProfile($actor, $users);
        }

        return DB::transaction(function () use ($users): array {
            $keepIds = ['ADMIN-000001'];

            foreach ($users as $raw) {
                if (! is_array($raw) || empty($raw['id'])) {
                    continue;
                }

                $portalId = (string) $raw['id'];
                $keepIds[] = $portalId;
                $user = User::query()->where('user_id', $portalId)->first();
                $fields = $this->userFields($raw, $user, $portalId);

                if (! empty($raw['password'])) {
                    $fields['password'] = $raw['password'];
                } elseif (! $user) {
                    $fields['password'] = str()->random(16);
                    $fields['mustChangePassword'] = true;
                }

                $user ? $user->fill($fields)->save() : User::query()->create($fields);
            }

            User::query()->whereNotIn('user_id', array_unique($keepIds))->delete();

            return $this->usersForJs();
        });
    }

    protected function syncOwnProfile(?User $actor, array $users): array
    {
        abort_if(! $actor, 403);

        $profile = collect($users)->first(
            fn (mixed $user): bool => is_array($user) && ($user['id'] ?? null) === $actor->user_id
        );

        if (! is_array($profile)) {
            return [$this->userToJs($actor)];
        }

        $allowed = [
            'firstName', 'lastName', 'middleName', 'contact', 'birthDate', 'birthPlace',
            'barangay', 'city', 'province', 'region', 'email', 'username', 'photo',
            'strand', 'address', 'guardianName', 'guardianContact',
        ];
        $fields = collect($allowed)
            ->filter(fn (string $field): bool => array_key_exists($field, $profile))
            ->mapWithKeys(fn (string $field): array => [$field => $profile[$field]])
            ->all();

        $actor->fill($fields)->save();

        return [$this->userToJs($actor)];
    }

    protected function userFields(array $raw, ?User $user, string $portalId): array
    {
        $fields = [
            'user_id' => $portalId,
            'role' => $raw['role'] ?? $user?->role ?? 'student',
            'status' => $raw['status'] ?? $user?->status ?? 'active',
            'firstName' => $raw['firstName'] ?? $user?->firstName ?? 'User',
            'lastName' => $raw['lastName'] ?? $user?->lastName ?? 'Account',
            'middleName' => $raw['middleName'] ?? $user?->middleName,
            'contact' => $raw['contact'] ?? $user?->contact ?? '00000000000',
            'birthDate' => $raw['birthDate'] ?? $user?->birthDate?->format('Y-m-d') ?? '2000-01-01',
            'birthPlace' => $raw['birthPlace'] ?? $user?->birthPlace ?? 'N/A',
            'barangay' => $raw['barangay'] ?? $user?->barangay ?? 'N/A',
            'city' => $raw['city'] ?? $user?->city ?? 'N/A',
            'province' => $raw['province'] ?? $user?->province ?? 'N/A',
            'region' => $raw['region'] ?? $user?->region ?? 'N/A',
            'email' => $raw['email'] ?? $user?->email ?? strtolower($portalId).'@digitech.local',
            'username' => $raw['username'] ?? $user?->username,
            'photo' => $raw['photo'] ?? $user?->photo,
            'strand' => $raw['strand'] ?? $user?->strand,
            'address' => $raw['address'] ?? $user?->address,
            'childId' => $raw['childId'] ?? $user?->childId,
            'childIds' => $raw['childIds'] ?? $user?->childIds ?? [],
            'guardianName' => $raw['guardianName'] ?? $user?->guardianName,
            'guardianContact' => $raw['guardianContact'] ?? $user?->guardianContact,
            'mustChangePassword' => (bool) ($raw['mustChangePassword'] ?? $user?->mustChangePassword ?? false),
            'employeeId' => $raw['employeeId'] ?? $user?->employeeId,
            'department' => $raw['department'] ?? $user?->department,
        ];

        $known = array_flip(array_merge(['id', 'password', 'rolePassword', 'createdAt', 'updatedAt'], array_keys($fields)));
        $fields['profile_extra'] = collect($raw)->except(array_keys($known))->all();

        return $fields;
    }

    protected function defaultSettings(): array
    {
        return [
            'theme' => 'light',
            'teacherRegistration' => true,
            'adminRegistration' => false,
            'institutionName' => 'Digitech College',
            'schoolYear' => date('Y').'-'.(date('Y') + 1),
            'passingGrade' => 75,
            'notifyStudents' => true,
            'notifyParents' => true,
            'notifyTeachers' => true,
            'notifyAdmins' => true,
        ];
    }
}
