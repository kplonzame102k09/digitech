<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\ParentLinkRequest;
use App\Models\PortalCollection;
use App\Models\Requirement;
use App\Models\SystemSetting;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /** Collections that are backed by a real Eloquent table. */
    private const DOMAIN_MODELS = [
        'enrollments' => Enrollment::class,
        'documentRequests' => DocumentRequest::class,
        'grades' => Grade::class,
        'competencies' => Competency::class,
        'notifications' => Notification::class,
        'announcements' => Announcement::class,
        'attendance' => Attendance::class,
        'auditLogs' => AuditLog::class,
        'requirements' => Requirement::class,
        'parentLinkRequests' => ParentLinkRequest::class,
    ];

    /** Column whitelist applied when a client pushes a collection back. */
    private const WRITE_FIELDS = [
        'enrollments' => ['id', 'studentId', 'status', 'programType', 'gradeLevel', 'strand', 'track', 'schoolYear', 'trainingLevel', 'assignedTeacherId', 'assignedSection', 'reviewNotes', 'rejectionReason', 'reviewedAt', 'reviewedBy'],
        'documentRequests' => ['id', 'studentId', 'documentType', 'purpose', 'copies', 'notes', 'status', 'requestDate', 'reviewNotes', 'rejectionReason', 'releaseMethod', 'releaseDate', 'reviewedAt', 'reviewedBy', 'createdBy'],
        'grades' => ['id', 'studentId', 'subject', 'semester', 'prelim', 'midterm', 'finals', 'finalGrade', 'units', 'schoolYear', 'teacherId', 'remarks', 'published', 'publishedAt', 'publishedBy', 'notes', 'updatedBy'],
        'competencies' => ['id', 'studentId', 'competency', 'qualification', 'status', 'assessmentDate', 'assessor', 'evidence', 'remarks', 'createdBy', 'updatedBy'],
        'notifications' => ['id', 'userId', 'title', 'message', 'read', 'source', 'recordId'],
        'announcements' => ['id', 'title', 'message', 'category', 'audience', 'authorId', 'createdBy', 'image'],
        'attendance' => ['id', 'studentId', 'date', 'status', 'subject', 'recordedBy', 'remarks'],
        'auditLogs' => ['id', 'entity', 'recordId', 'action', 'from', 'to', 'notes', 'reason', 'actorId', 'createdAt'],
        'requirements' => ['id', 'studentId', 'name', 'type', 'status', 'dueDate', 'submittedAt', 'notes', 'fileUrl'],
        'parentLinkRequests' => ['id', 'parentId', 'studentId', 'status', 'reviewedAt', 'reviewedBy'],
    ];

    /**
     * Columns that mark a domain row as owned by a specific user id. When a
     * non-admin pushes a collection, only rows matched by these columns may be
     * removed; admin payloads reconcile against the whole table.
     */
    private const OWNER_FIELDS = [
        'enrollments' => ['studentId'],
        'documentRequests' => ['studentId', 'createdBy'],
        'grades' => ['teacherId', 'updatedBy'],
        'competencies' => ['createdBy', 'updatedBy'],
        'notifications' => ['userId'],
        'announcements' => ['createdBy', 'authorId'],
        'attendance' => ['recordedBy'],
        'auditLogs' => ['actorId'],
        'requirements' => ['studentId'],
        'parentLinkRequests' => ['parentId', 'studentId'],
    ];

    public function bootPayload(?User $authUser = null): array
    {
        return [
            'csrf' => csrf_token(),
            'logoutUrl' => url('/logout'),
            'apiBase' => url('/api/portal'),
            'currentUser' => $authUser ? $this->userToJs($authUser) : null,
            'collections' => $this->allCollections($authUser),
        ];
    }

    public function allCollections(?User $actor = null): array
    {
        $data = ['users' => $this->usersForJs()];

        foreach (self::COLLECTION_KEYS as $key) {
            $data[$key] = $this->getCollection($key, $actor);
        }

        return $data;
    }

    public function getCollection(string $key, ?User $actor = null): mixed
    {
        if ($key === 'users') {
            return $this->usersForJs();
        }

        if ($key === 'settings') {
            return $this->settingsForJs();
        }

        abort_unless(in_array($key, self::COLLECTION_KEYS, true), 404, 'Unknown collection');

        $model = self::DOMAIN_MODELS[$key] ?? null;

        if (! $model) {
            return PortalCollection::query()->find($key)?->value ?? [];
        }

        $visibleStudentIds = in_array($key, ['requirements', 'documentRequests'], true)
            ? $this->visibleStudentIds($actor)
            : null;

        $rows = $model::query()
            ->when($visibleStudentIds !== null, fn (Builder $query) => $query->whereIn('studentId', $visibleStudentIds))
            ->when($key === 'auditLogs', fn ($query) => $query->orderByDesc('createdAt'), fn ($query) => $query->orderByDesc('created_at'))
            ->get();

        return $rows
            ->map(fn (Model $row): array => $this->serializeDomain($key, $row))
            ->values()
            ->all();
    }

    public function putCollection(string $key, mixed $value, ?User $actor = null): mixed
    {
        if ($key === 'users') {
            return $this->syncUsers(is_array($value) ? $value : [], $actor);
        }

        if ($key === 'settings') {
            abort_unless($actor?->isAdmin(), 403);
            $validated = is_array($value) ? $value : (json_decode(json_encode($value), true) ?? []);

            return $this->applySettings($validated, $actor);
        }

        abort_unless(in_array($key, self::COLLECTION_KEYS, true), 404, 'Unknown collection');
        abort_unless($this->canWriteCollection($actor, $key), 403);

        if (isset(self::DOMAIN_MODELS[$key])) {
            $this->syncDomainRows($key, is_array($value) ? $value : [], $actor);
        } else {
            PortalCollection::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? array_values($value) : []],
            );
        }

        return $this->getCollection($key, $actor);
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
            'photo' => $user->photo ? $this->publicPhotoUrl($user->photo) : '',
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
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     */
    public function importUsers(array $users): void
    {
        DB::transaction(function () use ($users): void {
            $this->upsertUsers($users);
        });
    }

    protected function syncUsers(array $users, ?User $actor): array
    {
        if (! $actor?->isAdmin()) {
            return $this->syncOwnProfile($actor, $users);
        }

        return DB::transaction(function () use ($users): array {
            $keepIds = ['ADMIN-000001'];

            $keepIds = array_merge($keepIds, $this->upsertUsers($users));

            User::query()->whereNotIn('user_id', array_unique($keepIds))->delete();

            return $this->usersForJs();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, string>
     */
    protected function upsertUsers(array $users): array
    {
        $portalIds = [];

        foreach ($users as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $raw = $this->normalizeImportedUser($raw);

            $portalId = (string) $raw['id'];
            $portalIds[] = $portalId;
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

        return $portalIds;
    }

    /**
     * Make an incoming user row tolerant to missing columns, values, or aliases.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function normalizeImportedUser(array $raw): array
    {
        $role = strtolower(trim((string) ($raw['role'] ?? '')));
        $allowedRoles = ['admin', 'teacher', 'student', 'parent', 'guest'];
        $role = in_array($role, $allowedRoles, true) ? $role : 'student';

        $status = strtolower(trim((string) ($raw['status'] ?? '')));
        $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

        $email = strtolower(trim((string) ($raw['email'] ?? '')));
        $email = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

        $password = (string) ($raw['password'] ?? '');
        $password = strlen($password) >= 6 ? $password : '';

        foreach (['firstName', 'lastName', 'middleName'] as $name) {
            if (isset($raw[$name]) && trim((string) $raw[$name]) === '') {
                $raw[$name] = null;
            }
        }

        $raw['role'] = $role;
        $raw['status'] = $status;
        $raw['email'] = $email;
        $raw['password'] = $password;
        $raw['id'] = ! empty($raw['id']) ? (string) $raw['id'] : $this->generateUserId($role);

        return $raw;
    }

    protected function generateUserId(string $role): string
    {
        $prefix = match ($role) {
            'student' => 'STU',
            'teacher' => 'TCH',
            'admin' => 'ADM',
            'parent' => 'PRT',
            'guest' => 'GST',
            default => 'USR',
        };

        do {
            $id = sprintf('%s-%s-%s', $prefix, now()->year, strtoupper(str()->random(6)));
        } while (User::query()->where('user_id', $id)->exists());

        return $id;
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
            'barangay', 'city', 'province', 'region', 'email', 'username',
            'strand', 'address', 'guardianName', 'guardianContact', 'photo',
            // Role-specific extras persisted inside profile_extra.
            'occupation', 'emergencyContact', 'specialization',
        ];
        $fields = collect($allowed)
            ->filter(fn (string $field): bool => array_key_exists($field, $profile))
            ->mapWithKeys(fn (string $field): array => [$field => $profile[$field]])
            ->all();

        if (array_key_exists('photo', $fields)) {
            $fields['photo'] = $this->normalizePhotoPath($fields['photo']);
        }

        $extras = [
            'occupation' => $fields['occupation'] ?? null,
            'emergencyContact' => $fields['emergencyContact'] ?? null,
            'specialization' => $fields['specialization'] ?? null,
        ];
        $extras = collect($extras)->filter(fn (mixed $value): bool => $value !== null && $value !== '')->all();

        $columnFields = collect($fields)->except(['occupation', 'emergencyContact', 'specialization'])->all();

        $beforeExtras = array_merge(
            ['occupation' => null, 'emergencyContact' => null, 'specialization' => null],
            is_array($actor->profile_extra) ? $actor->profile_extra : []
        );

        $extraChanges = collect($extras)
            ->filter(fn (mixed $value, string $key): bool => ($beforeExtras[$key] ?? null) !== $value)
            ->keys()
            ->all();

        $actor->fill($columnFields);
        $columnChanges = array_keys(collect($actor->getDirty())->except(['updated_at', 'profile_extra'])->all());
        $actor->save();

        if ($extraChanges !== []) {
            $actor->profile_extra = array_merge(
                is_array($actor->profile_extra) ? $actor->profile_extra : [],
                $extras
            );
            $actor->save();
        }

        $changed = array_unique(array_merge($columnChanges, $extraChanges));

        if ($changed !== []) {
            AuditLog::record([
                'entity' => AuditLog::ENTITY_USER,
                'recordId' => $actor->user_id,
                'action' => 'profile.updated',
                'notes' => ucfirst($actor->role).' profile updated ('.implode(', ', $changed).').',
                'actorId' => $actor->user_id,
            ]);
        }

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

        $known = array_flip(array_merge(['id', 'password', 'createdAt', 'updatedAt'], array_keys($fields)));
        $fields['profile_extra'] = collect($raw)->except(array_keys($known))->all();

        if (is_string($fields['photo'])) {
            $fields['photo'] = $this->normalizePhotoPath($fields['photo']);
        }

        return $fields;
    }

    protected function settingsForJs(): array
    {
        $settings = SystemSetting::getInstance();

        return [
            'theme' => $settings->theme ?: 'light',
            'teacherRegistration' => (bool) $settings->teacherRegistration,
            'adminRegistration' => (bool) $settings->adminRegistration,
            'institutionName' => $settings->institutionName ?: 'Digitech College',
            'schoolYear' => $settings->schoolYear ?: date('Y').'-'.(date('Y') + 1),
            'passingGrade' => $settings->passingGrade !== null ? (float) $settings->passingGrade : 75,
            'enrollmentDeadline' => $settings->enrollmentDeadline?->format('Y-m-d'),
            'notifyStudents' => (bool) $settings->notifyStudents,
            'notifyParents' => (bool) $settings->notifyParents,
            'notifyTeachers' => (bool) $settings->notifyTeachers,
            'notifyAdmins' => (bool) $settings->notifyAdmins,
            'updatedBy' => $settings->updatedBy,
            'updatedAt' => $this->dateToIso($settings->updated_at),
        ];
    }

    protected function applySettings(array $values, ?User $actor): array
    {
        $settings = SystemSetting::getInstance();

        $allowed = [
            'theme', 'teacherRegistration', 'adminRegistration', 'institutionName',
            'schoolYear', 'passingGrade', 'enrollmentDeadline', 'notifyStudents',
            'notifyParents', 'notifyTeachers', 'notifyAdmins',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $values)) {
                $settings->{$field} = $values[$field];
            }
        }

        $settings->updatedBy = $actor?->user_id;
        $settings->updated_at = now();
        $settings->save();

        return $this->settingsForJs();
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function syncDomainRows(string $key, array $records, ?User $actor): void
    {
        $model = self::DOMAIN_MODELS[$key];
        $allowed = self::WRITE_FIELDS[$key];

        // An empty payload is an explicit reset performed by an admin.
        if ($records === [] && $actor?->isAdmin()) {
            $model::query()->delete();

            return;
        }

        if ($records === []) {
            return;
        }

        foreach ($records as $raw) {
            if (! is_array($raw) || empty($raw['id'])) {
                continue;
            }

            $data = $this->scopedDomainFields($key, $raw, $allowed, $actor, $model);

            if ($data === null) {
                continue;
            }

            $this->upsertDomainRow($key, $model, $data, $actor);
        }

        $this->reconcileDomainRows($key, $model, $records, $actor);

        if ($key === 'notifications') {
            $this->dedupeNotifications($model);
        }
    }

    /**
     * Collapse duplicate notification rows so the bell/modals never list the
     * same event twice. Keeps the newest row for each user+source+record or,
     * when no record id is set, user+source+title+message.
     */
    protected function dedupeNotifications(string $model): void
    {
        $keep = $model::query()
            ->selectRaw('MAX(id) AS keep')
            ->groupBy('userId', 'source', DB::raw('COALESCE(recordId, CONCAT(title, ":|:", message))'))
            ->pluck('keep')
            ->all();

        if ($keep !== []) {
            $model::query()->whereNotIn('id', $keep)->delete();
        }
    }

    /**
     * Remove rows that were deleted on the client but are still on the server.
     * The payload is the client's current full view of the collection, so row
     * deletions arrive as "missing" ids. Non-admin writers may only remove rows
     * they own so one session cannot touch another user's data.
     */
    protected function reconcileDomainRows(string $key, string $model, array $records, ?User $actor): void
    {
        $ids = collect($records)->pluck('id')->filter()->values()->all();

        if ($ids === []) {
            return;
        }

        $query = $model::query()->whereNotIn('id', $ids);

        if (! $actor?->isAdmin()) {
            $ownerFields = self::OWNER_FIELDS[$key] ?? [];

            if ($ownerFields !== []) {
                $query->where(function (Builder $query) use ($ownerFields, $actor): void {
                    foreach ($ownerFields as $field) {
                        $query->orWhere($field, $actor?->user_id);
                    }
                });
            }
        }

        $query->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function scopedDomainFields(string $key, array $raw, array $allowed, ?User $actor, string $model): ?array
    {
        // Audit logs carry their timestamp under "date" in the legacy client.
        if ($key === 'auditLogs' && array_key_exists('date', $raw)) {
            $raw['createdAt'] = $raw['date'];
        }

        $data = collect($raw)->only($allowed)->all();

        if ($data === []) {
            return null;
        }

        switch ($key) {
            case 'enrollments':
                if ($actor?->isAdmin()) {
                    $current = $this->existingValue($model, $raw, 'status');
                    if (isset($data['status']) && $data['status'] !== $current) {
                        $data['reviewedAt'] = now();
                        $data['reviewedBy'] = $actor->user_id;
                    }
                } else {
                    $data['studentId'] = $actor?->user_id;
                    if (($data['status'] ?? null) && ! in_array($data['status'], ['Draft', 'Submitted'], true)) {
                        unset($data['status']);
                    }
                    $data = array_diff_key($data, array_flip(['assignedTeacherId', 'assignedSection', 'reviewNotes', 'rejectionReason', 'reviewedAt', 'reviewedBy']));
                }
                break;

            case 'grades':
                if ($actor?->isTeacher()) {
                    $data['teacherId'] = $actor->user_id;
                    $data['publishedBy'] = $data['published'] ? $actor->user_id : ($data['publishedBy'] ?? null);
                }

                if (! isset($data['semester'])) {
                    $data['semester'] = Grade::SEMESTER_FIRST;
                }

                if (! in_array($data['semester'], [Grade::SEMESTER_FIRST, Grade::SEMESTER_SECOND], true)) {
                    $data['semester'] = Grade::SEMESTER_FIRST;
                }

                // Recompute the weighted final grade whenever term grades change.
                $prelim = $data['prelim'] ?? null;
                $midterm = $data['midterm'] ?? null;
                $finals = $data['finals'] ?? null;

                if ($prelim !== null || $midterm !== null || $finals !== null) {
                    $data['finalGrade'] = round(
                        ((float) ($prelim ?? 0) * Grade::PRELIM_WEIGHT) +
                        ((float) ($midterm ?? 0) * Grade::MIDTERM_WEIGHT) +
                        ((float) ($finals ?? 0) * Grade::FINALS_WEIGHT),
                        2
                    );
                }

                break;

            case 'competencies':
                if ($actor?->isTeacher()) {
                    $data['updatedBy'] = $actor->user_id;
                }
                break;

            case 'documentRequests':
                if (! $actor?->isAdmin()) {
                    // The local mirror once contained the whole collection, so a
                    // requester's payload can hold other students' rows. Only rows
                    // they already own may be updated, and brand-new rows must name
                    // themselves as the student, so ownership can never move.
                    $existing = $model::query()->find($raw['id']);

                    $ownsRow = $existing
                        ? (string) $existing->studentId === (string) $actor->user_id
                            || (string) $existing->createdBy === (string) $actor->user_id
                        : (string) ($raw['studentId'] ?? '') === (string) $actor->user_id;

                    if (! $ownsRow) {
                        return null;
                    }

                    $data['studentId'] = $actor->user_id;

                    if ($existing) {
                        if ($existing->createdBy !== null) {
                            $data['createdBy'] = (string) $existing->createdBy;
                        } else {
                            unset($data['createdBy']);
                        }
                    } else {
                        $data['createdBy'] = $actor->user_id;
                    }

                    // Submissions are final: a requester may create a Pending
                    // request but never rewrite the registrar's decision.
                    $data['status'] = $existing ? (string) $existing->status : 'Pending';

                    $data = array_diff_key($data, array_flip(['reviewNotes', 'rejectionReason', 'releaseMethod', 'releaseDate', 'reviewedAt', 'reviewedBy']));
                }
                break;

            case 'requirements':
                if (! $actor?->isAdmin()) {
                    // Clients mirror the whole (previously unscoped) collection
                    // locally, so a student's payload can contain other pupils'
                    // rows. Students may only update learner-controlled fields on
                    // requirement rows they already own; every other record
                    // (other pupils' rows, or new rows with fresh ids) is
                    // skipped so ownership and admin-assigned metadata never move.
                    $existing = $model::query()->find($raw['id']);

                    if (! $existing || (string) $existing->studentId !== (string) $actor->user_id) {
                        return null;
                    }

                    $data = collect($data)->only(['id', 'status', 'submittedAt', 'notes', 'fileUrl', 'studentId'])->all();
                    $data['studentId'] = $actor->user_id;

                    // Review decisions ("Approved"/"Rejected") are admin-only;
                    // an unset status preserves the stored value.
                    if (isset($data['status']) && ! in_array($data['status'], ['Pending', 'Submitted'], true)) {
                        unset($data['status']);
                    }
                }
                break;

            case 'notifications':
                // Clients push the whole notifications collection back. Non-admins
                // may only act on notifications they already own (marking them
                // read); existing rows keep their stored recipient so one user can
                // never hijack another's rows. Brand-new rows, however, are relayed
                // alerts created for OTHER users (e.g. a parent's link request
                // notifying admins), so their declared recipient must survive.
                if (! $actor?->isAdmin()) {
                    $existing = $model::query()->find($raw['id']);
                    if ($existing) {
                        $data['userId'] = (string) $existing->userId;
                    }
                }
                break;

            case 'announcements':
                if (! $actor?->isAdmin()) {
                    $data['authorId'] = $actor?->user_id;
                }
                break;

            case 'auditLogs':
                $data['actorId'] = $actor?->user_id;
                break;

            case 'parentLinkRequests':
                // Parents may only submit requests in their own name. Once an
                // admin approves a request that decision is final and must
                // survive the parent's next (stale) push. A fresh submission
                // reuses the row for the same parent+student (unique index), so
                // a re-request after a rejection cannot collide on (parentId,
                // studentId).
                if ($actor?->isParent()) {
                    $data['parentId'] = $actor->user_id;
                    $existing = $model::query()->find($raw['id']);

                    if (! $existing) {
                        $existing = $model::query()
                            ->where('parentId', $data['parentId'])
                            ->where('studentId', $data['studentId'] ?? null)
                            ->first();

                        if ($existing) {
                            $data['id'] = $existing->getKey();
                        }
                    }

                    $data['status'] = $existing && $existing->status === 'Approved' ? 'Approved' : 'Pending';
                    unset($data['reviewedAt'], $data['reviewedBy']);
                }
                break;
        }

        return $data;
    }

    protected function existingValue(?string $model, array $raw, string $field): mixed
    {
        return $model::query()->find($raw['id'])?->{$field};
    }

    /**
     * Resolve which students a non-admin may read for the student-scoped
     * collections. Students see their own rows, a parent sees their linked
     * children, and every other role keeps the full view.
     *
     * @return array<int, string>|null null means "no restriction"
     */
    protected function visibleStudentIds(?User $actor): ?array
    {
        if (! $actor || $actor->isAdmin()) {
            return null;
        }

        if ($actor->isStudent()) {
            return [$actor->user_id];
        }

        if ($actor->isParent()) {
            $ids = collect([$actor->childId])
                ->concat($actor->childIds ?? [])
                ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
                ->all();

            // Pivot-backed approvals (parent_student links primary keys, not
            // portal ids), so map them back to the student user_id.
            $pivotIds = DB::table('parent_student')
                ->join('users as linked', 'linked.id', '=', 'parent_student.student_id')
                ->where('parent_student.parent_id', $actor->getKey())
                ->pluck('linked.user_id')
                ->all();

            return array_values(array_unique(array_merge($ids, $pivotIds)));
        }

        return null;
    }

    protected function upsertDomainRow(string $key, string $model, array $data, ?User $actor): void
    {
        if ($key !== 'auditLogs') {
            $row = $model::query()->find($data['id']);

            if ($row) {
                $row->fill($data)->save();
            } else {
                $model::query()->create($data);
            }
        } else {
            $this->upsertAuditLog($model, $data, $actor);
        }

        if ($key === 'parentLinkRequests' && $actor?->isAdmin() && strtolower((string) ($data['status'] ?? '')) === 'approved') {
            $this->attachParentStudent($data['parentId'] ?? null, $data['studentId'] ?? null);
        }
    }

    protected function upsertAuditLog(string $model, array $data, ?User $actor): void
    {
        $existing = $model::query()->find($data['id']);

        if ($existing) {
            $existing->fill(collect($data)->except('createdAt')->all());
            $existing->actorId = $actor?->user_id ?? $existing->actorId;
            $existing->save();
        } else {
            $data['createdAt'] = $data['createdAt'] ?? now();
            $model::query()->create($data);
        }
    }

    protected function attachParentStudent(?string $parentId, ?string $studentId): void
    {
        if (! $parentId || ! $studentId) {
            return;
        }

        $parentKey = User::query()->where('user_id', $parentId)->value('id');
        $studentKey = User::query()->where('user_id', $studentId)->value('id');

        if ($parentKey && $studentKey) {
            DB::table('parent_student')->updateOrInsert(
                ['parent_id' => $parentKey, 'student_id' => $studentKey],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    protected function canWriteCollection(?User $actor, string $key): bool
    {
        if (! $actor) {
            return false;
        }

        return match ($actor->role) {
            'admin' => true,
            'teacher' => in_array($key, ['announcements', 'attendance', 'competencies', 'grades', 'notifications', 'auditLogs'], true),
            'student' => in_array($key, ['documentRequests', 'enrollments', 'notifications', 'requirements', 'users', 'auditLogs'], true),
            'parent' => in_array($key, ['notifications', 'parentLinkRequests', 'users', 'auditLogs'], true),
            'guest' => in_array($key, ['documentRequests', 'notifications'], true),
            default => false,
        };
    }

    protected function serializeDomain(string $key, Model $row): array
    {
        return match ($key) {
            'enrollments' => [
                'id' => $row->id,
                'studentId' => $row->studentId,
                'status' => $row->status,
                'programType' => $row->programType,
                'gradeLevel' => $row->gradeLevel,
                'strand' => $row->strand,
                'track' => $row->track,
                'schoolYear' => $row->schoolYear,
                'trainingLevel' => $row->trainingLevel,
                'assignedTeacherId' => $row->assignedTeacherId,
                'assignedSection' => $row->assignedSection,
                'reviewNotes' => $row->reviewNotes,
                'rejectionReason' => $row->rejectionReason,
                'reviewedAt' => $this->dateToIso($row->reviewedAt),
                'reviewedBy' => $row->reviewedBy,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'grades' => [
                'id' => $row->id,
                'studentId' => $row->studentId,
                'subject' => $row->subject,
                'semester' => $row->semester,
                'prelim' => $row->prelim !== null ? (float) $row->prelim : null,
                'midterm' => $row->midterm !== null ? (float) $row->midterm : null,
                'finals' => $row->finals !== null ? (float) $row->finals : null,
                'finalGrade' => $row->finalGrade !== null ? (float) $row->finalGrade : null,
                'units' => $row->units !== null ? (float) $row->units : 1.00,
                'schoolYear' => $row->schoolYear,
                'teacher' => $row->teacherUserId?->firstName.($row->teacherUserId?->lastName !== null ? ' '.$row->teacherUserId->lastName : ''),
                'teacherId' => $row->teacherId,
                'remarks' => $row->remarks,
                'published' => (bool) $row->published,
                'publishedAt' => $this->dateToIso($row->publishedAt),
                'publishedBy' => $row->publishedBy,
                'notes' => $row->notes,
                'updatedBy' => $row->updatedBy,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'attendance' => [
                'id' => $row->id,
                'studentId' => $row->studentId,
                'date' => $this->dateToString($row->date),
                'status' => $row->status,
                'subject' => $row->subject,
                'recordedBy' => $row->recordedBy,
                'remarks' => $row->remarks,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'documentRequests' => [
                'id' => $row->id,
                'studentId' => $row->studentId,
                'documentType' => $row->documentType,
                'purpose' => $row->purpose,
                'copies' => (int) $row->copies,
                'notes' => $row->notes,
                'status' => $row->status,
                'requestDate' => $this->dateToIso($row->requestDate),
                'reviewNotes' => $row->reviewNotes,
                'rejectionReason' => $row->rejectionReason,
                'releaseMethod' => $row->releaseMethod,
                'releaseDate' => $this->dateToIso($row->releaseDate),
                'reviewedAt' => $this->dateToIso($row->reviewedAt),
                'reviewedBy' => $row->reviewedBy,
                'createdBy' => $row->createdBy,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'requirements' => [
                'id' => $row->id,
                'studentId' => $row->studentId,
                'name' => $row->name,
                'type' => $row->type,
                'status' => $row->status,
                'dueDate' => $this->dateToString($row->dueDate),
                'submittedAt' => $this->dateToIso($row->submittedAt),
                'notes' => $row->notes,
                'fileUrl' => $row->fileUrl,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'competencies' => [
                'id' => $row->id,
                'studentId' => $row->studentId,
                'competency' => $row->competency,
                'qualification' => $row->qualification,
                'status' => $row->status,
                'assessmentDate' => $this->dateToString($row->assessmentDate),
                'assessor' => $row->assessor,
                'evidence' => $row->evidence,
                'remarks' => $row->remarks,
                'createdBy' => $row->createdBy,
                'updatedBy' => $row->updatedBy,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'notifications' => [
                'id' => $row->id,
                'userId' => $row->userId,
                'title' => $row->title,
                'message' => $row->message,
                'read' => (bool) $row->read,
                'source' => $row->source,
                'recordId' => $row->recordId,
                'date' => $this->dateToIso($row->created_at),
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'announcements' => [
                'id' => $row->id,
                'title' => $row->title,
                'message' => $row->message,
                'category' => $row->category,
                'audience' => $row->audience,
                'authorId' => $row->authorId,
                'createdBy' => (string) $row->createdBy,
                'image' => (string) $row->image,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            'auditLogs' => [
                'id' => $row->id,
                'entity' => $row->entity,
                'recordId' => $row->recordId,
                'action' => $row->action,
                'from' => $row->from,
                'to' => $row->to,
                'notes' => $row->notes,
                'reason' => $row->reason,
                'actorId' => $row->actorId,
                'date' => $this->dateToIso($row->createdAt),
                'createdAt' => $this->dateToIso($row->createdAt),
            ],
            'parentLinkRequests' => [
                'id' => $row->id,
                'parentId' => $row->parentId,
                'studentId' => $row->studentId,
                'status' => $row->status,
                'reviewedAt' => $this->dateToIso($row->reviewedAt),
                'reviewedBy' => $row->reviewedBy,
                'createdAt' => $this->dateToIso($row->created_at),
                'updatedAt' => $this->dateToIso($row->updated_at),
            ],
            default => $row->toArray(),
        };
    }

    protected function publicPhotoUrl(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://', '/', 'data:'])) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    protected function normalizePhotoPath(mixed $photo): ?string
    {
        if (! is_string($photo) || trim($photo) === '') {
            return null;
        }

        if (Str::startsWith($photo, ['http://', 'https://', 'data:'])) {
            return $photo;
        }

        return preg_replace('#^/?storage(?:/|$)#', '', $photo);
    }

    protected function dateToIso(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->toIso8601String();
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function dateToString(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? substr($value, 0, 10) : null;
    }
}
