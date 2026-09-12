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
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /** Category buckets a strand/track row may belong to. */
    private const PROGRAM_CATEGORIES = ['academics', 'techpro'];

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

    /** Valid registrar document types recognized by the portal. */
    private const DOCUMENT_TYPES = [
        'Form 137',
        'Good Moral Certificate',
        'Transcript of Records',
        'Diploma',
    ];

    /** Valid enrollment lifecycle states. */
    private const ENROLLMENT_STATUSES = [
        'Draft',
        'Submitted',
        'Under Review',
        'Approved',
        'Rejected',
        'Needs Correction',
        'Enrolled',
    ];

    /** PSGC reference tables used to turn stored location codes into names. */
    private const LOCATION_TABLES = [
        'region' => ['philippine_regions', 'region_code', 'name'],
        'province' => ['philippine_provinces', 'province_code', 'name'],
        'city' => ['philippine_cities', 'city_code', 'name'],
        'barangay' => ['philippine_barangays', 'psgc_code', 'name'],
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
        $enrolledStudentIds = $this->getEnrolledStudentIds($actor);

        $data = ['users' => $this->usersForJs($actor, $enrolledStudentIds)];

        foreach (self::COLLECTION_KEYS as $key) {
            $data[$key] = $this->getCollection($key, $actor, $enrolledStudentIds);
        }

        // Parents must be able to confirm a student id when requesting a link
        // before the student is linked to them. Their users collection only
        // carries themselves plus already-linked children, so expose a minimal
        // id/name directory (no contact or record data) for that lookup.
        if ($actor?->isParent()) {
            $data['studentDirectory'] = $this->studentDirectory();
        }

        return $data;
    }

    /**
     * Minimal, read-only listing of every student account so parents can
     * verify an id before submitting a link request. Carries no contact or
     * academic data; the server re-validates on every write anyway.
     *
     * @return array<int, array{id: string, firstName: ?string, lastName: ?string, status: string}>
     */
    public function studentDirectory(): array
    {
        return User::query()
            ->where('role', 'student')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->user_id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'status' => $user->status ?: 'active',
            ])
            ->values()
            ->all();
    }

    /**
     * The student portal ids a teacher may read. Teachers operate under an
     * assigned-only scope so a fresh adviser never sees the whole student body.
     * Returns null for non-teachers; an (possibly empty) array for teachers.
     *
     * @return array<int, string>|null
     */
    public function getEnrolledStudentIds(?User $actor): ?array
    {
        if (! $actor?->isTeacher()) {
            return null;
        }

        return collect([
            DB::table('enrollments')->where('assignedTeacherId', $actor->user_id)->pluck('studentId')->all(),
            DB::table('grades')->where('teacherId', $actor->user_id)->pluck('studentId')->all(),
            DB::table('competencies')->where('createdBy', $actor->user_id)->orWhere('updatedBy', $actor->user_id)->pluck('studentId')->all(),
            DB::table('attendance')->where('recordedBy', $actor->user_id)->pluck('studentId')->all(),
        ])
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    public function getCollection(string $key, ?User $actor = null, ?array $enrolledStudentIds = null): mixed
    {
        if ($key === 'users') {
            return $this->usersForJs($actor, $enrolledStudentIds);
        }

        if ($key === 'settings') {
            return $this->settingsForJs();
        }

        abort_unless(in_array($key, self::COLLECTION_KEYS, true), 404, 'Unknown collection');

        $model = self::DOMAIN_MODELS[$key] ?? null;

        if (! $model) {
            return PortalCollection::query()->find($key)?->value ?? [];
        }

        $studentIds = $enrolledStudentIds ?? $this->getEnrolledStudentIds($actor);

        $query = $model::query();

        if ($key === 'auditLogs') {
            // Audit trails are private: non-admins only see rows they authored.
            $query->when(! $actor?->isAdmin(), fn (Builder $q) => $q->where('actorId', $actor?->user_id));
        } elseif ($key === 'announcements') {
            $audiences = match (true) {
                $actor?->isAdmin() => null,
                $actor?->isTeacher() => ['All', 'all', 'Students', 'Student', 'Teachers', 'Teacher'],
                $actor?->isStudent() => ['All', 'all', 'Students', 'Student'],
                $actor?->isParent() => ['All', 'all', 'Parents', 'Parent', 'Students', 'Student'],
                $actor?->isGuest() => ['All', 'all', 'Guests', 'Guest'],
                default => null,
            };

            $query->when($audiences !== null, function (Builder $q) use ($actor, $audiences): void {
                $q->where(function (Builder $inner) use ($actor, $audiences): void {
                    $inner->whereIn('audience', $audiences)
                        ->orWhere('authorId', $actor?->user_id);
                });
            });
        } elseif ($key === 'parentLinkRequests') {
            $query->when(! ($actor?->isAdmin() ?? false), function (Builder $q) use ($actor, $studentIds): void {
                if ($actor?->isParent()) {
                    $q->where('parentId', $actor->user_id);
                } elseif ($actor?->isStudent()) {
                    $q->where('studentId', $actor->user_id);
                } elseif ($actor?->isTeacher()) {
                    // Advisers may review link requests for their own advisees,
                    // never the whole registry.
                    $q->whereIn('studentId', $studentIds ?? []);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        } elseif ($key === 'notifications') {
            $query->when(! $actor?->isAdmin(), fn (Builder $q) => $q->where('userId', $actor?->user_id));
        } elseif (in_array($key, ['requirements', 'documentRequests', 'grades', 'attendance', 'competencies', 'enrollments'], true)) {
            $visible = $actor?->isTeacher()
                ? ($studentIds ?? [])
                : $this->visibleStudentIds($actor);

            $query->when($visible !== null, fn (Builder $q) => $q->whereIn('studentId', $visible));
        }

        if ($key === 'auditLogs') {
            $query->orderByDesc('createdAt');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->get()
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
            try {
                $this->syncDomainRows($key, is_array($value) ? $value : [], $actor);
            } catch (QueryException $exception) {
                throw_if(! $this->isUniqueViolation($exception), $exception);

                abort(422, 'Duplicate records are not allowed in this collection.');
            }
        } else {
            PortalCollection::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? array_values($value) : []],
            );
        }

        return $this->getCollection($key, $actor);
    }

    public function usersForJs(?User $actor = null, ?array $enrolledStudentIds = null): array
    {
        $enrolledStudentIds = $enrolledStudentIds ?? $this->getEnrolledStudentIds($actor);

        $query = User::query();

        if ($actor && ! $actor->isAdmin()) {
            if ($actor->isParent()) {
                $childIds = collect([$actor->childId])
                    ->concat($actor->childIds ?? [])
                    ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
                    ->all();

                $query->where(function (Builder $q) use ($actor, $childIds): void {
                    $q->where('user_id', $actor->user_id)
                        ->orWhereIn('user_id', $childIds);
                });
            } else {
                $query->where(function (Builder $q) use ($actor, $enrolledStudentIds): void {
                    $q->where('user_id', $actor->user_id);

                    if ($actor->isTeacher() && $enrolledStudentIds !== null) {
                        $q->orWhereIn('user_id', $enrolledStudentIds);
                    }
                });
            }
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->userToJs($user))
            ->values()
            ->all();
    }

    /** Resolve a stored PSGC code to the readable name shown on-screen. When the
     *  value is already a human-readable name (or not found in the reference
     *  table) it is returned unchanged so the profile keeps working for users
     *  who already have correct names stored. */
    protected function resolveLocationName(string $field, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        [$table, $codeColumn, $nameColumn] = self::LOCATION_TABLES[$field] ?? [];

        if (! $table) {
            return $value;
        }

        $code = trim($value);

        $name = DB::table($table)
            ->where($codeColumn, $code)
            ->value($nameColumn);

        return is_string($name) && $name !== '' ? $name : $value;
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
            'barangay' => $this->resolveLocationName('barangay', $user->barangay),
            'city' => $this->resolveLocationName('city', $user->city),
            'province' => $this->resolveLocationName('province', $user->province),
            'region' => $this->resolveLocationName('region', $user->region),
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

            $skipped = $this->skippedDuplicateEmails();
            $this->skippedDuplicateEmails = 0;

            if ($skipped > 0) {
                AuditLog::record([
                    'entity' => AuditLog::ENTITY_USER,
                    'action' => 'import.skipped',
                    'notes' => "{$skipped} user(s) skipped because their email address was already in use.",
                    'actorId' => auth()->id(),
                ]);
            }
        });
    }

    protected function syncUsers(array $users, ?User $actor): array
    {
        if (! $actor?->isAdmin()) {
            return $this->syncOwnProfile($actor, $users);
        }

        return DB::transaction(function () use ($users, $actor): array {
            // An admin payload must never be allowed to remove the acting
            // account from the active administrator role.
            $this->assertNotAdminSelfModification($users, $actor);

            $upserted = $this->upsertUsers($users);

            // Admin accounts are never deleted through the portal sync.
            $adminIds = User::query()->where('role', 'admin')->pluck('user_id')->all();

            // Treat the payload as a full replacement only when it includes the
            // acting admin. Empty, stale, or partial payloads must not wipe rows.
            $isFullReplacement = $users !== [] && collect($users)->contains(
                fn (mixed $user): bool => is_array($user) && ($user['id'] ?? null) === $actor?->user_id
            );

            if ($isFullReplacement) {
                $keepIds = array_unique(array_merge($upserted, $adminIds));
                $deleteIds = User::query()
                    ->whereNotIn('user_id', $keepIds)
                    ->pluck('user_id')
                    ->all();

                if ($deleteIds !== []) {
                    $blocked = array_values(array_filter(
                        $deleteIds,
                        fn (string $userId): bool => $this->userHasRelatedRecords($userId),
                    ));

                    if ($blocked !== []) {
                        abort(
                            422,
                            'Cannot delete account(s): '.implode(', ', $blocked)
                                .' still own related records. Deactivate them instead.',
                        );
                    }

                    User::query()->whereIn('user_id', $deleteIds)->delete();
                }
            }

            // Demoting or deactivating admins must never leave the portal
            // without an active administrator; this runs after the upsert so a
            // violation rolls back the entire transaction.
            $activeAdmins = User::query()->where('role', 'admin')->where('status', 'active')->count();
            if ($activeAdmins < 1) {
                abort(422, 'You cannot deactivate or demote the last active admin account.');
            }

            return $this->usersForJs();
        });
    }

    /**
     * Reject a payload that deactivates or demotes the acting admin in their
     * own push.
     *
     * @param  array<int, array<string, mixed>>  $users
     */
    protected function assertNotAdminSelfModification(array $users, ?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        foreach ($users as $raw) {
            if (! is_array($raw) || (string) ($raw['id'] ?? '') !== $actor->user_id) {
                continue;
            }

            $role = $raw['role'] ?? $actor->role;
            $status = strtolower((string) ($raw['status'] ?? $actor->status ?? 'active'));

            if ($role !== 'admin' || $status !== 'active') {
                abort(422, 'You cannot deactivate or demote your own admin account.');
            }
        }
    }

    /**
     * Whether deleting a user account would cascade-destroy any domain rows.
     * Mirrors the client's deactivate-instead-of-delete advice for every table
     * whose foreign key cascades on delete.
     */
    protected function userHasRelatedRecords(string $userId): bool
    {
        $checks = [
            ['enrollments', 'studentId'],
            ['grades', 'studentId'],
            ['grades', 'teacherId'],
            ['attendance', 'studentId'],
            ['requirements', 'studentId'],
            ['document_requests', 'studentId'],
            ['competencies', 'studentId'],
            ['parent_link_requests', 'parentId'],
            ['parent_link_requests', 'studentId'],
            ['notifications', 'userId'],
        ];

        foreach ($checks as [$table, $column]) {
            if (DB::table($table)->where($column, $userId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, string>
     */
    protected function upsertUsers(array $users): array
    {
        $portalIds = [];
        $seenEmails = [];
        $skippedDuplicateEmails = 0;

        foreach ($users as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $raw = $this->normalizeImportedUser($raw);

            $portalId = (string) $raw['id'];
            $user = User::query()->where('user_id', $portalId)->first();
            $fields = $this->userFields($raw, $user, $portalId);

            // Duplicate email handling. The users table enforces NOT NULL on
            // email, so a conflicting email is reverted for existing accounts
            // and brand-new accounts with a taken email are skipped.
            $email = strtolower(trim((string) ($fields['email'] ?? '')));
            if ($email !== '') {
                $ownedBy = User::query()
                    ->where('email', $email)
                    ->where('id', '!=', $user?->id)
                    ->first();
                if ($ownedBy !== null || isset($seenEmails[$email])) {
                    if ($user?->id !== null) {
                        $fields['email'] = $user->email;
                    } else {
                        $skippedDuplicateEmails++;

                        continue;
                    }
                } else {
                    $seenEmails[$email] = true;
                }
            }

            if (! empty($raw['password'])) {
                $fields['password'] = $raw['password'];
            } elseif (! $user) {
                $fields['password'] = str()->random(16);
                $fields['mustChangePassword'] = true;
            }

            $user ? $user->fill($fields)->save() : User::query()->create($fields);
            $portalIds[] = $portalId;
        }

        $this->skippedDuplicateEmails += $skippedDuplicateEmails;

        return $portalIds;
    }

    protected int $skippedDuplicateEmails = 0;

    /**
     * Per-student counts of brand-new unpublished grade rows pushed by a
     * teacher in the current sync; flushed to the registrar afterwards as one
     * summary alert per student.
     *
     * @var array<string, int>
     */
    protected array $pendingGradeAlerts = [];

    public function skippedDuplicateEmails(): int
    {
        return $this->skippedDuplicateEmails;
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
        $password = strlen($password) >= 12 ? $password : '';

        foreach (['firstName', 'lastName', 'middleName'] as $name) {
            if (isset($raw[$name]) && trim((string) $raw[$name]) === '') {
                $raw[$name] = null;
            }
        }

        foreach (['address', 'region', 'province', 'city', 'barangay'] as $location) {
            if (isset($raw[$location])) {
                $value = trim((string) $raw[$location]);
                $raw[$location] = $value !== '' ? $value : null;
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

        // Validate email format and uniqueness before saving.
        if (array_key_exists('email', $columnFields)) {
            $email = strtolower(trim((string) $columnFields['email']));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                abort(422, 'Please provide a valid email address.');
            }

            $emailTaken = User::query()
                ->where('email', $email)
                ->where('user_id', '!=', $actor->user_id)
                ->exists();

            if ($emailTaken) {
                abort(422, 'That email address is already in use.');
            }

            $columnFields['email'] = $email;
        }

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

        // Free-text address columns are fine, but a value that matches no known
        // Philippine region/province/city/barangay is almost always a typo.
        // Revert it to the stored value so the registry stays consistent.
        $fields = $this->coerceUserLocation($fields, $user);

        return $fields;
    }

    /**
     * Re-validate the free-text location columns against the reference tables.
     * A value is accepted when it equals a known code or a name (case-
     * insensitive); anything else falls back to the previously stored value.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function coerceUserLocation(array $fields, ?User $user): array
    {
        $maps = [
            'region' => ['philippine_regions', 'region_code', 'name'],
            'province' => ['philippine_provinces', 'province_code', 'name'],
            'city' => ['philippine_cities', 'city_code', 'name'],
            'barangay' => ['philippine_barangays', 'psgc_code', 'name'],
        ];

        foreach ($maps as $field => [$table, $codeColumn, $nameColumn]) {
            $value = is_string($fields[$field] ?? null) ? trim($fields[$field]) : '';

            if ($value === '' || $value === 'N/A') {
                continue;
            }

            $known = DB::table($table)->where($codeColumn, $value)->exists()
                || DB::table($table)->whereRaw('LOWER('.$nameColumn.') = ?', [mb_strtolower($value)])->exists();

            if (! $known) {
                $fields[$field] = $user?->{$field} ?? null;
            }
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
            'programs' => collect((array) ($settings->programs ?? []))
                ->filter()
                ->map(fn (mixed $row): array => [
                    'name' => is_array($row) ? trim((string) ($row['name'] ?? '')) : trim((string) $row),
                    'description' => is_array($row) ? trim((string) ($row['description'] ?? '')) : '',
                    'category' => $this->normalizeCategory(is_array($row) ? ($row['category'] ?? null) : null),
                ])
                ->values()
                ->all(),
            'tvetQualifications' => collect((array) ($settings->tvetQualifications ?? []))
                ->filter()
                ->map(fn (mixed $row): array => [
                    'name' => is_array($row) ? trim((string) ($row['name'] ?? '')) : trim((string) $row),
                    'description' => is_array($row) ? trim((string) ($row['description'] ?? '')) : '',
                    'levels' => collect(is_array($row) ? ($row['levels'] ?? []) : [])
                        ->map(static fn (mixed $level): string => trim((string) $level))
                        ->filter()
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'tvetLevels' => array_values(array_filter((array) ($settings->tvetLevels ?? []))),
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
            'schoolYear', 'passingGrade', 'enrollmentDeadline',
            'programs', 'tvetQualifications', 'tvetLevels',
            'notifyStudents', 'notifyParents', 'notifyTeachers', 'notifyAdmins',
        ];

        $values = $this->normalizeCatalogueValues($values);

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
     * Coerce catalogue settings into their canonical shapes. Strings and rows
     * are both accepted for TVET qualifications, and legacy plain-string
     * entries are upgraded in place.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalizeCatalogueValues(array $values): array
    {
        if (array_key_exists('programs', $values)) {
            $values['programs'] = $this->normalizeNamedRows($values['programs'], false, true);
        }

        if (array_key_exists('tvetQualifications', $values)) {
            $values['tvetQualifications'] = $this->normalizeNamedRows($values['tvetQualifications'], true);
        }

        if (array_key_exists('tvetLevels', $values)) {
            $values['tvetLevels'] = collect((array) $values['tvetLevels'])
                ->map(static fn (mixed $item): string => trim((string) $item))
                ->filter(static fn (string $item): bool => $item !== '')
                ->unique()
                ->values()
                ->all();
        }

        return $values;
    }

    /**
     * Coerce a catalogue into canonical name/description rows. Plain strings
     * and row arrays are both accepted; nameless and duplicate entries are
     * dropped. When $withLevels is true (TVET), each row also keeps a
     * deduped levels list. When $withCategory is true (strands/tracks), each
     * row keeps one of the PROGRAM_CATEGORIES buckets.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeNamedRows(mixed $value, bool $withLevels, bool $withCategory = false): array
    {
        $rows = [];
        $seen = [];

        foreach ((array) $value as $row) {
            $name = is_array($row)
                ? trim((string) ($row['name'] ?? ''))
                : trim((string) $row);

            if ($name === '' || in_array($name, $seen, true)) {
                continue;
            }

            $seen[] = $name;

            $entry = [
                'name' => $name,
                'description' => is_array($row) ? trim((string) ($row['description'] ?? '')) : '',
            ];

            if ($withCategory) {
                $entry['category'] = $this->normalizeCategory($row['category'] ?? null);
            }

            if ($withLevels) {
                $entry['levels'] = collect(is_array($row) ? ($row['levels'] ?? []) : [])
                    ->map(static fn (mixed $item): string => trim((string) $item))
                    ->filter(static fn (string $item): bool => $item !== '')
                    ->unique()
                    ->values()
                    ->all();
            }

            $rows[] = $entry;
        }

        return $rows;
    }

    /**
     * Map an arbitrary category label onto one of the configured program
     * buckets. Anything unrecognised defaults to the first category.
     */
    protected function normalizeCategory(mixed $category): string
    {
        $value = strtolower(trim((string) ($category ?? '')));

        return in_array($value, self::PROGRAM_CATEGORIES, true)
            ? $value
            : self::PROGRAM_CATEGORIES[0];
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

        if ($key === 'grades' && $this->pendingGradeAlerts !== []) {
            foreach ($this->pendingGradeAlerts as $studentId => $count) {
                $this->createAdminAlert(
                    'New grades submitted',
                    $this->displayName($studentId)." has {$count} new grade".($count === 1 ? '' : 's').' awaiting review.',
                    'grade',
                    $studentId,
                );
            }
            $this->pendingGradeAlerts = [];
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
     * Whether an actor may drop a brand-new notification row into another
     * user's inbox. The actor itself, every admin (the registrar relay), and
     * a teacher's own assigned students plus their linked parents are
     * legitimate relays; any other recipient is treated as spoofing and the
     * row is rejected on write.
     */
    protected function canReceiveNotification(?User $actor, string $recipientId): bool
    {
        if ($actor === null || $recipientId === '') {
            return false;
        }

        if ($recipientId === $actor->user_id) {
            return true;
        }

        if (User::query()->where('user_id', $recipientId)->where('role', 'admin')->exists()) {
            // Students, parents, teachers, and guests are the only legitimate
            // cross-role relay paths to the registrar/admins. Guests are kept
            // in because their only writable collection (documentRequests)
            // legitimately alerts the registrar on submission.
            return in_array($actor->role, ['student', 'parent', 'teacher', 'guest'], true);
        }

        if (! $actor->isTeacher()) {
            return false;
        }

        $enrolled = $this->getEnrolledStudentIds($actor) ?? [];

        if ($enrolled === []) {
            return false;
        }

        if (in_array($recipientId, $enrolled, true)) {
            return true;
        }

        $parentUserIds = DB::table('parent_student')
            ->join('users as linked', 'linked.id', '=', 'parent_student.student_id')
            ->join('users as parentUser', 'parentUser.id', '=', 'parent_student.parent_id')
            ->whereIn('linked.user_id', $enrolled)
            ->where('parentUser.role', 'parent')
            ->where('parentUser.status', 'active')
            ->pluck('parentUser.user_id')
            ->all();

        if (in_array($recipientId, $parentUserIds, true)) {
            return true;
        }

        // Parents whose link is stored in profile columns rather than the
        // pivot table still count, mirroring the client-side parentIdsFor().
        $columnLinked = User::query()
            ->where('role', 'parent')
            ->where('status', 'active')
            ->get(['user_id', 'childId', 'childIds'])
            ->filter(fn (User $parent): bool => count(array_intersect(
                collect([$parent->childId])
                    ->concat($parent->childIds ?? [])
                    ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                    ->all(),
                $enrolled,
            )) > 0)
            ->pluck('user_id')
            ->all();

        return in_array($recipientId, $columnLinked, true);
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
                // Registry-backed strand/track values only: anything else is a
                // typo or a stale record, so it is reverted to the stored value
                // (or left unset on a brand-new row) instead of saved.
                $data = $this->applyEnrollmentCatalogues($data, $model, $raw);

                if ($actor?->isAdmin()) {
                    $current = $this->existingValue($model, $raw, 'status');
                    if (isset($data['status']) && $data['status'] !== $current) {
                        $data['reviewedAt'] = now();
                        $data['reviewedBy'] = $actor->user_id;
                    }

                    // Only known states are writable.
                    if (isset($data['status']) && ! in_array($data['status'], self::ENROLLMENT_STATUSES, true)) {
                        $data['status'] = $current ?? 'Draft';
                    }
                    // An Enrolled record is terminal: it can never regress to
                    // any earlier lifecycle state, not just Draft/Submitted.
                    if (isset($data['status']) && $current === 'Enrolled' && $data['status'] !== 'Enrolled') {
                        unset($data['status']);
                    }

                    // assignedTeacherId must resolve to a real teacher account.
                    if (isset($data['assignedTeacherId'])) {
                        $teacher = User::query()
                            ->where('user_id', (string) $data['assignedTeacherId'])
                            ->where('role', 'teacher')
                            ->exists();
                        if (! $teacher) {
                            unset($data['assignedTeacherId']);
                        }
                    }

                    // Section names stay bounded and printable. Invalid names
                    // keep the stored value so a stale payload never wipes one.
                    if (isset($data['assignedSection']) && ! $this->validSection($data['assignedSection'])) {
                        $kept = $this->existingValue($model, $raw, 'assignedSection');
                        if ($kept !== null) {
                            $data['assignedSection'] = $kept;
                        } else {
                            unset($data['assignedSection']);
                        }
                    }
                } else {
                    // Ownership is immutable. An existing row may only be touched
                    // by the student it belongs to, and its studentId can never be
                    // reassigned; brand-new rows always name the actor.
                    $existing = $model::query()->find($raw['id']);

                    if ($existing) {
                        if ((string) $existing->studentId !== (string) $actor?->user_id) {
                            return null;
                        }
                        $data['studentId'] = (string) $existing->studentId;
                    } else {
                        $data['studentId'] = $actor?->user_id;

                        // One pending enrollment per student per school year.
                        $duplicate = Enrollment::query()
                            ->where('studentId', $actor?->user_id)
                            ->where('schoolYear', $data['schoolYear'] ?? null)
                            ->whereIn('status', ['Draft', 'Submitted'])
                            ->exists();

                        if ($duplicate) {
                            abort(422, 'You already have a pending enrollment for this school year.');
                        }
                    }

                    if (($data['status'] ?? null) && ! in_array($data['status'], ['Draft', 'Submitted'], true)) {
                        unset($data['status']);
                    }
                    $data = array_diff_key($data, array_flip(['assignedTeacherId', 'assignedSection', 'reviewNotes', 'rejectionReason', 'reviewedAt', 'reviewedBy']));
                }
                break;

            case 'grades':
                if ($actor?->isTeacher()) {
                    // A teacher may only work on their own rows. Publishing is a
                    // registrar decision: a teacher push can never flip a grade to
                    // published, and existing rows keep the stored approval state.
                    $existing = $model::query()->find($raw['id']);

                    if ($existing) {
                        if ((string) $existing->teacherId !== (string) $actor->user_id) {
                            return null;
                        }

                        $data['published'] = (bool) $existing->published;
                        $data['publishedAt'] = $existing->publishedAt;
                        $data['publishedBy'] = $existing->publishedBy;
                    } else {
                        $data['published'] = false;
                        unset($data['publishedAt']);
                    }
                    unset($data['publishedBy']);

                    $data['teacherId'] = $actor->user_id;
                }

                if (! isset($data['semester'])) {
                    $data['semester'] = Grade::SEMESTER_FIRST;
                }

                if (! in_array($data['semester'], [Grade::SEMESTER_FIRST, Grade::SEMESTER_SECOND], true)) {
                    $data['semester'] = Grade::SEMESTER_FIRST;
                }

                // Recompute the weighted final grade whenever term grades change.
                // Missing terms are ignored: the weights resample across the
                // terms actually present rather than treating gaps as zeroes.
                $prelim = $data['prelim'] ?? null;
                $midterm = $data['midterm'] ?? null;
                $finals = $data['finals'] ?? null;

                // School grades live on a 0-100 scale; anything outside that
                // range is clamped on the way in.
                foreach (['prelim', 'midterm', 'finals', 'finalGrade'] as $term) {
                    if (isset($data[$term]) && $data[$term] !== null && $data[$term] !== '') {
                        $data[$term] = max(0, min(100, (float) $data[$term]));
                    }
                }

                $prelim = $data['prelim'] ?? $prelim;
                $midterm = $data['midterm'] ?? $midterm;
                $finals = $data['finals'] ?? $finals;
                $terms = [
                    [Grade::PRELIM_WEIGHT, $prelim],
                    [Grade::MIDTERM_WEIGHT, $midterm],
                    [Grade::FINALS_WEIGHT, $finals],
                ];
                $present = collect($terms)
                    ->filter(fn (array $pair): bool => $pair[1] !== null && $pair[1] !== '')
                    ->map(fn (array $pair): array => [(float) $pair[0], (float) $pair[1]]);

                if (! $present->isEmpty()) {
                    $weightedSum = $present->sum(fn (array $pair): float => $pair[0] * $pair[1]);
                    $totalWeight = $present->sum(fn (array $pair): float => $pair[0]);
                    $data['finalGrade'] = round($weightedSum / $totalWeight, 2);
                }

                break;

            case 'attendance':
                // A free-text status field is otherwise a garbage-in vector;
                // only the states the UI emits are accepted.
                if (isset($data['status']) && ! in_array($data['status'], ['Present', 'Late', 'Absent', 'Excused'], true)) {
                    $data['status'] = 'Present';
                }
                break;

            case 'competencies':
                if ($actor?->isTeacher()) {
                    $data['updatedBy'] = $actor->user_id;
                }
                break;

            case 'documentRequests':
                // The registrar recognises a fixed set of document types; every
                // writer (admin included) is constrained to that whitelist.
                if (isset($data['documentType'])) {
                    $type = trim((string) $data['documentType']);
                    if (! in_array($type, self::DOCUMENT_TYPES, true)) {
                        $type = 'Form 137';
                    }
                    $data['documentType'] = $type;
                }

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
                    // request or withdraw their own open one, but never rewrite
                    // the registrar's decision.
                    if ($existing) {
                        $stored = (string) $existing->status;
                        $requested = strtolower((string) ($data['status'] ?? ''));
                        $data['status'] = $stored;
                        if ($requested === 'cancelled'
                            && ! in_array($stored, ['Released', 'Approved', 'Rejected'], true)) {
                            $data['status'] = 'Cancelled';
                        }
                    } else {
                        $data['status'] = 'Pending';

                        // Prevent duplicate requests for the same document and
                        // purpose while an earlier one is still in progress.
                        $duplicate = DocumentRequest::query()
                            ->where('studentId', $actor->user_id)
                            ->where('documentType', $data['documentType'])
                            ->where('purpose', trim((string) ($data['purpose'] ?? '')))
                            ->whereIn('status', ['Pending', 'Processing', 'Ready for Release'])
                            ->exists();

                        if ($duplicate) {
                            abort(422, 'You already have an open request for this document.');
                        }
                    }

                    $data = array_diff_key($data, array_flip(['reviewNotes', 'rejectionReason', 'releaseMethod', 'releaseDate', 'reviewedAt', 'reviewedBy']));
                }
                break;

            case 'requirements':
                if ($actor?->isAdmin()) {
                    if (isset($data['status']) && ! in_array($data['status'], ['Pending', 'Submitted', 'Approved', 'Rejected'], true)) {
                        unset($data['status']);
                    }
                    break;
                }
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

                    // Replacing a submitted file removes the previous upload.
                    if (isset($data['fileUrl']) && $existing->fileUrl && $data['fileUrl'] !== $existing->fileUrl) {
                        $oldName = basename((string) $existing->fileUrl);
                        if (preg_match('/^[A-Za-z0-9_.-]+$/', $oldName)) {
                            @Storage::disk('private')->delete('requirement-files/'.$oldName);
                        }
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
                // never hijack another's rows. Brand-new rows are relayed alerts
                // created for OTHER users (e.g. a parent's link request notifying
                // admins, or a submission notifying the registrar), so their
                // declared recipient is validated against the actor's legitimate
                // reach instead of being trusted blindly.
                if (! $actor?->isAdmin()) {
                    $existing = $model::query()->find($raw['id']);

                    if ($existing) {
                        $data['userId'] = (string) $existing->userId;
                    } elseif (! $this->canReceiveNotification($actor, (string) ($data['userId'] ?? ''))) {
                        return null;
                    }
                }
                break;

            case 'announcements':
                // Replacing the banner removes the previous upload so orphaned
                // images never pile up on the public disk.
                if (isset($data['image'])) {
                    $existingRow = $this->existingValue($model, $raw, 'image');
                    if ($existingRow && $data['image'] !== $existingRow) {
                        $oldName = basename((string) $existingRow);
                        if (preg_match('/^[A-Za-z0-9_.-]+$/', $oldName)) {
                            @Storage::disk('public')->delete('announcement-images/'.$oldName);
                        }
                    }
                }

                if (! $actor?->isAdmin()) {
                    // Only the original author may edit an announcement; new
                    // rows are credited to the actor so authorship can't move.
                    $existing = $model::query()->find($raw['id']);

                    if ($existing) {
                        if ((string) ($existing->authorId ?? '') !== (string) ($actor?->user_id ?? '')) {
                            return null;
                        }
                        $data['authorId'] = (string) $existing->authorId;
                    } else {
                        $data['authorId'] = $actor?->user_id;
                    }
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

                    // A link request must name a student that actually exists
                    // in the portal; otherwise an approval would attach the
                    // parent to a record that is not there.
                    $studentId = (string) ($data['studentId'] ?? '');
                    if ($studentId === '' || ! User::query()
                        ->where('user_id', $studentId)
                        ->where('role', 'student')
                        ->exists()) {
                        abort(422, 'No student record found for that ID.');
                    }

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
        return isset($raw['id']) ? $model::query()->find($raw['id'])?->{$field} : null;
    }

    /**
     * Enforce the registrar's strand/track catalogues on an incoming enrollment
     * row. Values outside the allow-list are reverted to the stored value on an
     * existing record and dropped on a brand-new one, mirroring the
     * documentType whitelist behaviour further down this file.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * Resolve an admission catalogue for the given key. Strands and tracks are
     * a single unified program list: a non-empty `programs` setting overrides
     * both, otherwise each key falls back to its config default. TVET lists
     * are managed independently and default to empty (no gating).
     *
     * @return array<int, string>
     */
    public function catalogue(string $key): array
    {
        $settings = SystemSetting::getInstance();

        $override = in_array($key, ['strands', 'tracks'], true)
            ? (array) ($settings->programs ?? [])
            : (array) ($settings->{$key} ?? []);

        $fallback = (array) config("portal.{$key}", []);

        $values = $override !== [] ? $override : $fallback;

        return collect($values)
            ->map(static fn (mixed $item): string => is_array($item)
                ? trim((string) ($item['name'] ?? ''))
                : trim((string) $item))
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The training levels attached to a named TVET qualification. Falls back
     * to the global level list when the qualification is unknown or unnamed.
     *
     * @return array<int, string>
     */
    public function tvetLevelsForQualification(string $name): array
    {
        $rows = SystemSetting::getInstance()->tvetQualifications ?? [];

        foreach ((array) $rows as $row) {
            $rowName = is_array($row)
                ? trim((string) ($row['name'] ?? ''))
                : trim((string) $row);

            if (strtolower($rowName) === strtolower(trim($name))) {
                return collect(is_array($row) ? ($row['levels'] ?? []) : [])
                    ->map(static fn (mixed $item): string => trim((string) $item))
                    ->filter(static fn (string $item): bool => $item !== '')
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return $this->catalogue('tvetLevels');
    }

    protected function applyEnrollmentCatalogues(array $data, string $model, array $raw): array
    {
        $strands = $this->catalogue('strands');
        $tracks = $this->catalogue('tracks');
        $programType = trim((string) ($data['programType'] ?? $raw['programType'] ?? $this->existingValue($model, $raw, 'programType') ?? ''));
        $isTvet = $programType === 'TVET';

        if (isset($data['strand']) && $strands !== [] && ! in_array($data['strand'], $strands, true)) {
            $kept = $this->existingValue($model, $raw, 'strand');
            if ($kept !== null) {
                $data['strand'] = (string) $kept;
            } elseif (isset($raw['id']) && array_key_exists('strand', $data)) {
                unset($data['strand']);
            }
        }

        // TVET records carry a qualification name as their track; Senior High
        // records use the unified strand/track list instead.
        $trackCatalogue = $isTvet ? $this->catalogue('tvetQualifications') : $tracks;

        if (isset($data['track']) && $trackCatalogue !== [] && ! in_array($data['track'], $trackCatalogue, true)) {
            $kept = $this->existingValue($model, $raw, 'track');
            if ($kept !== null) {
                $data['track'] = (string) $kept;
            } elseif (array_key_exists('track', $data)) {
                unset($data['track']);
            }
        }

        return $data;
    }

    protected function validSection(mixed $section): bool
    {
        if (! is_string($section) || trim($section) === '') {
            return false;
        }

        $pattern = (string) config('portal.section_pattern', '/^[A-Za-z0-9][A-Za-z0-9 .,\'\/\-]{0,49}$/');

        return (bool) preg_match($pattern, str_replace("\r", '', $section));
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

        if ($actor->isGuest()) {
            // Guests submit document requests in their own name, so they may
            // only read rows that name them as the student. Every other
            // learner-scoped collection stays empty for them.
            return [$actor->user_id];
        }

        if ($actor->isTeacher()) {
            return $this->getEnrolledStudentIds($actor);
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
        $existing = null;
        $previousStatus = null;
        $before = null;

        if ($key !== 'auditLogs') {
            $existing = $model::query()->find($data['id']);

            if ($existing) {
                // Snapshot the stored state before fill() mutates the model, so
                // transition detection (e.g. Draft -> Submitted alerts, or an
                // administrator's approval decision) compares against the values
                // that actually were persisted.
                $before = $existing->getAttributes();
                $previousStatus = (string) ($before['status'] ?? '');
                $existing->fill($data)->save();
            } else {
                $model::query()->create($data);
            }
        } else {
            $this->upsertAuditLog($model, $data, $actor);
        }

        if ($key === 'parentLinkRequests' && $actor?->isAdmin() && strtolower((string) ($data['status'] ?? '')) === 'approved') {
            $this->attachParentStudent($data['parentId'] ?? null, $data['studentId'] ?? null);
        }

        $this->recordAdminDecision($key, $data, $actor, $before);

        $this->alertAdmins($key, $data, $actor, $existing, $previousStatus);
    }

    /**
     * Persist a server-side audit row whenever an administrator changes a
     * learner-facing decision. The client pages also record activity locally,
     * but the registrar's decisions must survive even when a stale or bare
     * client forgets to. Re-pushed payloads collapse onto the same row instead
     * of stacking duplicates.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $before
     */
    protected function recordAdminDecision(string $key, array $data, ?User $actor, ?array $before): void
    {
        if (! $actor?->isAdmin() || $before === null) {
            return;
        }

        $action = null;
        $notes = null;

        $statusChanged = isset($data['status']) && (string) ($before['status'] ?? '') !== (string) $data['status'];

        if ($key === 'enrollments') {
            $next = (string) ($data['status'] ?? '');
            if ($statusChanged && in_array($next, ['Approved', 'Rejected', 'Under Review', 'Needs Correction', 'Enrolled'], true)) {
                $action = 'enrollment.'.strtolower(str_replace(' ', '-', $next));
                $notes = 'Enrollment '.$data['id'].' moved from '.($before['status'] ?? '').' to '.$next.'.';
            }
        } elseif ($key === 'grades') {
            if (! (bool) ($before['published'] ?? false) && (bool) ($data['published'] ?? false)) {
                $action = 'grade.published';
                $notes = 'Grade '.($data['id'] ?? '').' published for student '.($data['studentId'] ?? '').'.';
            }
        } elseif ($key === 'documentRequests') {
            if ($statusChanged) {
                $action = 'document.'.strtolower(str_replace(' ', '-', (string) $data['status']));
                $notes = 'Document request '.$data['id'].' moved from '.($before['status'] ?? '').' to '.$data['status'].'.';
            }
        } elseif ($key === 'parentLinkRequests') {
            if ((string) ($data['status'] ?? '') === 'Approved' && (string) ($before['status'] ?? '') !== 'Approved') {
                $action = 'parentLink.approved';
                $notes = 'Parent '.($data['parentId'] ?? '').' linked to student '.($data['studentId'] ?? '').'.';
            }
        }

        if ($action === null) {
            return;
        }

        $alreadyRecorded = AuditLog::query()
            ->where('recordId', (string) ($data['id'] ?? ''))
            ->where('action', $action)
            ->where('actorId', $actor->user_id)
            ->exists();

        if (! $alreadyRecorded) {
            AuditLog::record([
                'entity' => AuditLog::ENTITY_ENROLLMENT,
                'recordId' => (string) ($data['id'] ?? ''),
                'action' => (string) $action,
                'notes' => (string) $notes,
                'actorId' => $actor->user_id,
            ]);
        }
    }

    /**
     * Mint registrar/admin alerts for the submission flows that originate on
     * the client. Recipients are resolved server-side because the client-side
     * users mirror no longer lists admins (privacy scoping), which is why the
     * old browser observer could not see anyone to notify.
     *
     * @param  array<string, mixed>  $data
     */
    protected function alertAdmins(string $key, array $data, ?User $actor, ?Model $existing, ?string $previousStatus = null): void
    {
        if ($actor === null) {
            return;
        }

        $status = (string) ($data['status'] ?? '');
        $isNew = $existing === null;

        if ($key === 'enrollments' && $actor->isStudent()) {
            if ($status === 'Submitted' && ($isNew || $previousStatus !== 'Submitted')) {
                $this->createAdminAlert(
                    'New enrollment submitted',
                    $this->displayName((string) $data['studentId']).' submitted enrollment '.$data['id'].' for review.',
                    'enrollment',
                    (string) $data['id'],
                );
            }
        } elseif ($key === 'requirements' && $actor->isStudent()) {
            // Students may only flip the status on their own existing rows.
            if ($status === 'Submitted' && ! $isNew && $previousStatus !== 'Submitted') {
                $this->createAdminAlert(
                    'Requirement submitted',
                    $this->displayName((string) $data['studentId']).' submitted '.($data['name'] ?? null ?: 'a requirement').' for review.',
                    'requirement',
                    (string) $data['id'],
                );
            }
        } elseif ($key === 'documentRequests' && ($actor->isStudent() || $actor->isGuest())) {
            if ($isNew) {
                $this->createAdminAlert(
                    'New document request',
                    $this->displayName((string) $data['studentId']).' requested '.($data['documentType'] ?? null ?: 'a document').'.',
                    'document',
                    (string) $data['id'],
                );
            }
        } elseif ($key === 'parentLinkRequests' && $actor->isParent()) {
            if ($isNew) {
                $this->createAdminAlert(
                    'Parent link request',
                    $this->displayName((string) $data['parentId']).' requested a link to '.$data['studentId'].'.',
                    'parentLink',
                    (string) $data['id'],
                );
            }
        } elseif ($key === 'announcements' && $actor->isTeacher()) {
            if ($isNew) {
                $this->createAdminAlert(
                    'Teacher announcement published',
                    $this->displayName($actor->user_id).' published '.($data['title'] ?? null ?: 'an announcement').'.',
                    'announcement',
                    (string) $data['id'],
                );
            }
        } elseif ($key === 'grades' && $actor->isTeacher()) {
            // Aggregate new unpublished rows per student; syncDomainRows flushes
            // one summary alert per student after the batch so a bulk entry
            // produces a single registrar alert instead of one per row.
            if ($isNew && ! (bool) ($data['published'] ?? false) && ! empty($data['studentId'])) {
                $this->pendingGradeAlerts[(string) $data['studentId']] = ($this->pendingGradeAlerts[(string) $data['studentId']] ?? 0) + 1;
            }
        }
    }

    /**
     * Create an alert notification row for every active admin, honouring the
     * registrar's "notify admins" toggle. An unread row already matching the
     * same recipient/source/record is left untouched so re-pushes collapse
     * instead of stacking (the same contract dedupeNotifications applies to
     * client-pushed notifications).
     */
    protected function createAdminAlert(string $title, string $message, string $source, ?string $recordId = null): void
    {
        if ((bool) (SystemSetting::getInstance()->notifyAdmins ?? true) === false) {
            return;
        }

        $adminIds = User::query()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->pluck('user_id')
            ->all();

        if ($adminIds === []) {
            return;
        }

        foreach ($adminIds as $adminId) {
            $exists = Notification::query()
                ->where('userId', $adminId)
                ->where('source', $source)
                ->where('read', false);

            if ($recordId !== null && $recordId !== '') {
                $exists->where('recordId', $recordId);
            } else {
                $exists->where('title', $title)->where('message', $message);
            }

            if ($exists->exists()) {
                continue;
            }

            Notification::query()->create([
                'id' => $this->generateNotificationId(),
                'userId' => $adminId,
                'title' => $title,
                'message' => $message,
                'read' => false,
                'source' => $source,
                'recordId' => $recordId,
            ]);
        }
    }

    protected function generateNotificationId(): string
    {
        do {
            $id = 'NOT-'.date('Y').'-'.strtoupper(Str::random(6));
        } while (Notification::query()->whereKey($id)->exists());

        return $id;
    }

    protected function displayName(string $userId): string
    {
        $user = User::query()->where('user_id', $userId)->first();

        if (! $user) {
            return 'A user';
        }

        return trim($user->firstName.' '.$user->lastName) ?: $userId;
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

    public function canWriteCollection(?User $actor, string $key): bool
    {
        if (! $actor) {
            return false;
        }

        return match ($actor->role) {
            'admin' => true,
            'teacher' => in_array($key, ['announcements', 'attendance', 'competencies', 'grades', 'notifications', 'users'], true),
            'student' => in_array($key, ['documentRequests', 'enrollments', 'notifications', 'requirements', 'users'], true),
            'parent' => in_array($key, ['notifications', 'parentLinkRequests', 'users'], true),
            'guest' => in_array($key, ['documentRequests', 'notifications', 'users'], true),
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
                'teacher' => $row->teacherUser?->firstName.($row->teacherUser?->lastName !== null ? ' '.$row->teacherUser->lastName : ''),
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
                'fileUrl' => $this->sanitizeRequirementUrl($row->fileUrl),
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

    /**
     * Normalize a requirement file reference before it reaches the browser.
     * Private-disk paths and legacy /storage URLs are rewritten to the
     * ownership-checked download route; unknown schemes (javascript:, data:,
     * vbscript:, …) are dropped so a crafted fileUrl can never execute.
     */
    protected function sanitizeRequirementUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('#^/api/portal/requirements/files/[A-Za-z0-9_.-]+$#', $url)) {
            return $url;
        }

        if (preg_match('#^requirement-files/([A-Za-z0-9_.-]+)$#', $url, $matches)) {
            return url('/api/portal/requirements/files/'.rawurlencode($matches[1]));
        }

        if (preg_match('#(?:^|/)storage/requirement-files/([A-Za-z0-9_.-]+)(?:\?.*)?$#', $url, $matches)) {
            return url('/api/portal/requirements/files/'.rawurlencode($matches[1]));
        }

        if (preg_match('#^https?://#i', $url) || str_starts_with($url, '//')) {
            return $url;
        }

        return null;
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

    protected function isUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'unique constraint');
    }
}
