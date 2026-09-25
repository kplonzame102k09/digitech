<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\LoginAttemptGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitorController extends Controller
{
    /** Session is considered "online" when seen within this many seconds. */
    private const ONLINE_WINDOW = 300;

    /** Bound on rows returned per poll so the page stays cheap to refresh. */
    private const ACTIVITY_LIMIT = 100;

    private const ALLOWED_ROLES = ['student', 'teacher', 'admin', 'parent', 'guest'];

    /** Cache key prefix for login attempts */
    private const LOGIN_KEY_PREFIX = 'login:';

    public function index()
    {
        return view('admin.live-monitoring');
    }

    /**
     * Lift a brute-force sign-in lock (per-account and/or per-IP) so a user
     * who contacted the admin can log in again without waiting out the lock.
     */
    public function unlock(Request $request): JsonResponse
    {
        $identifier = trim((string) $request->input('identifier', ''));
        $role = (string) $request->input('role', '');
        $ip = trim((string) $request->input('ip', ''));

        if ($identifier === '' && $ip === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Provide an account identifier and/or an IP address to unlock.',
            ], 422);
        }

        if ($identifier !== '' && ! in_array($role, self::ALLOWED_ROLES, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'A valid role is required to unlock an account.',
            ], 422);
        }

        $cleared = [];
        if ($identifier !== '') {
            $cleared = array_merge($cleared, LoginAttemptGuard::unlockAccount($identifier, $role));
        }
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $cleared = array_merge($cleared, LoginAttemptGuard::unlockIp($ip));
        }

        $cleared = array_values(array_unique($cleared));

        AuditLog::record([
            'entity' => AuditLog::ENTITY_USER,
            'recordId' => $identifier !== '' ? $identifier : ($ip !== '' ? $ip : ''),
            'action' => 'login_unlock',
            'notes' => 'Sign-in lock lifted by '.trim(($request->user()->firstName ?? '').' '.($request->user()->lastName ?? ''))
                .' for account '.($identifier !== '' ? $identifier.' ('.$role.')' : '')
                .($identifier !== '' && $ip !== '' ? ' and ' : '')
                .($ip !== '' ? 'IP '.$ip : '')
                .'. Cleared '.count($cleared).' lock key(s).',
            'actorId' => $request->user()->user_id,
        ]);

        return response()->json([
            'ok' => true,
            'cleared' => $cleared,
            'identifier' => $identifier !== '' ? $identifier : null,
            'role' => $identifier !== '' ? $role : null,
            'ip' => $ip !== '' ? $ip : null,
        ]);
    }

    /**
     * Manually block an IP so every login attempt from it is rejected until
     * an admin unlocks it. Accounts still auto-lock after repeated failures;
     * IP blocks are admin-initiated only.
     */
    public function lockIp(Request $request): JsonResponse
    {
        $ip = trim((string) $request->input('ip', ''));

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return response()->json([
                'ok' => false,
                'error' => 'Enter a valid IP address to block.',
            ], 422);
        }

        $actor = trim(($request->user()->firstName ?? '').' '.($request->user()->lastName ?? ''));

        LoginAttemptGuard::lockIp($ip, $actor);

        AuditLog::record([
            'entity' => AuditLog::ENTITY_USER,
            'recordId' => $ip,
            'action' => 'ip_manual_lock',
            'notes' => 'IP '.$ip.' blocked by '.($actor ?: 'an administrator')
                .'. The block stays in effect until it is manually unlocked.',
            'actorId' => $request->user()->user_id,
        ]);

        return response()->json([
            'ok' => true,
            'ip' => $ip,
            'lockedAt' => now()->toIso8601String(),
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $window = max(1, min(1440, (int) $request->input('window', 30)));
        $type = $request->input('type', 'all');
        $event = $request->input('event', 'all');
        $role = $request->input('role', '');
        $search = trim((string) $request->input('search', ''));

        $rows = $this->buildActivityQuery($type, $event, $role, $search)
            ->where('created_at', '>=', now()->subMinutes($window))
            ->orderByDesc('created_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        $activity = $rows->map(fn (ActivityLog $row): array => $this->serializeRow($row));

        $activityIds = $activity->pluck('id');
        $online = $this->onlineSessions();
        $lockedAccounts = $this->getLockedAccounts();

        return response()->json([
            'ok' => true,
            'now' => now()->toIso8601String(),
            'activity' => $activity,
            'online' => $online,
            'users' => $this->userDirectory($online),
            'lockedAccounts' => $lockedAccounts,
            'stats' => $this->stats($window, count($online)),
        ]);
    }

    protected function buildActivityQuery(string $type, string $event, string $role, string $search)
    {
        $query = ActivityLog::query()->with('user:id,user_id,firstName,lastName,role');

        if ($type === 'page') {
            $query->where('path', 'not like', 'api/%');
        } elseif ($type === 'api') {
            $query->where('path', 'like', 'api/%');
        }

        if ($event === 'security') {
            $query->whereNotNull('kind');
        }

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('firstName', 'like', "%{$search}%")
                            ->orWhere('lastName', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    protected function serializeRow(ActivityLog $row): array
    {
        $context = $row->context ? json_decode($row->context, true) : null;

        return [
            'id' => $row->id,
            'userId' => $row->user_id,
            'role' => $row->role,
            'actor' => $row->user
                ? trim($row->user->firstName.' '.$row->user->lastName)
                : null,
            'kind' => $row->kind,
            'targetRole' => is_array($context) ? ($context['role'] ?? null) : null,
            'target' => is_array($context) ? ($context['target'] ?? null) : null,
            'method' => $row->method,
            'path' => $row->path,
            'ip' => $row->ip_address,
            'ua' => $row->user_agent,
            'status' => $row->status,
            'time' => $row->created_at?->toIso8601String(),
        ];
    }

    /**
     * Every portal user with current online status. The directory powers the
     * admin map so it can render all users (online pinned by IP, offline at
     * the campus fallback) with phone as the identity label.
     */
    protected function userDirectory(array $online): array
    {
        $onlineByUserId = collect($online)->keyBy('userId');
        $portal = app(\App\Services\PortalDataService::class);

        return User::query()
            ->orderBy('role')
            ->orderBy('firstName')
            ->get(['user_id', 'firstName', 'lastName', 'role', 'contact', 'region', 'province', 'city', 'barangay', 'profile_extra'])
            ->map(function (User $user) use ($onlineByUserId, $portal): array {
                $current = $onlineByUserId->get($user->user_id);
                $extra = is_array($user->profile_extra) ? $user->profile_extra : [];
                $gps = $extra['_gps'] ?? null;

                return [
                    'userId' => $user->user_id,
                    'name' => trim($user->firstName.' '.$user->lastName),
                    'role' => $user->role,
                    'phone' => $user->contact,
                    'online' => $current !== null,
                    'ip' => $current['ip'] ?? null,
                    'home' => $portal->resolveHomeCentroid($user),
                    'gps' => is_array($gps) && isset($gps['lat'], $gps['lon'])
                        ? ['lat' => (float) $gps['lat'], 'lon' => (float) $gps['lon'], 'at' => $gps['at'] ?? null]
                        : null,
                    'lastActivity' => $current['lastActivity'] ?? null,
                    'ago' => $current['ago'] ?? null,
                ];
            })
            ->all();
    }

    protected function onlineSessions(): array
    {
        $cutoff = now()->subSeconds(self::ONLINE_WINDOW)->getTimestamp();

        // Get the most recent session for each user to avoid duplicates
        return DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('sessions.last_activity', '>=', $cutoff)
            ->whereNotNull('sessions.user_id')
            ->orderByDesc('sessions.last_activity')
            ->limit(50)
            ->get([
                'users.user_id',
                'users.firstName',
                'users.lastName',
                'users.role',
                'users.contact',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
            ])
            ->unique('user_id')
            ->values()
            ->map(function ($row): array {
                return [
                    'userId' => $row->user_id,
                    'name' => trim($row->firstName.' '.$row->lastName),
                    'role' => $row->role,
                    'phone' => $row->contact,
                    'ip' => $row->ip_address,
                    'ua' => $row->user_agent,
                    'lastActivity' => $row->last_activity,
                    'ago' => max(0, (int) now()->getTimestamp() - $row->last_activity),
                ];
            })
            ->all();
    }

    protected function stats(int $window, int $onlineNow): array
    {
        $activeHourCutoff = now()->subHour()->getTimestamp();
        $onlineSessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $activeHourCutoff)
            ->count();

        $fiveMin = now()->subMinutes(5);
        $recent5m = ActivityLog::query()
            ->where('created_at', '>=', $fiveMin)
            ->count();

        $securityToday = ActivityLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('kind', ['login_failed', 'login_blocked', 'blocked'])
            ->count();

        $today = ActivityLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, COUNT(*) AS hits')
            ->groupBy('ip_address')
            ->orderByDesc('hits')
            ->limit(5)
            ->get();

        return [
            'onlineNow' => $onlineNow,
            'activeHour' => $onlineSessions,
            'requests5m' => $recent5m,
            'securityToday' => $securityToday,
            'topIps' => $today->map(fn ($row): array => [
                'ip' => $row->ip_address,
                'hits' => (int) $row->hits,
            ]),
        ];
    }

    /**
     * Get currently locked accounts from cache/limiter
     * Returns list of locked accounts with their identifiers, roles, and lock expiry times
     */
    protected function getLockedAccounts(): array
    {
        $lockedAccounts = [];

        // Get recent login lockout audit logs to identify potentially locked accounts
        $recentLockouts = AuditLog::query()
            ->where('action', 'login_lockout')
            ->where('createdAt', '>=', now()->subMinutes(LoginAttemptGuard::LOCK_MINUTES))
            ->get();

        foreach ($recentLockouts as $lockout) {
            $identifier = $lockout->recordId;

            // Parse the notes to extract role if available
            $lockoutRole = 'unknown';
            if (preg_match('/(\w+) account/i', $lockout->notes, $matches)) {
                $lockoutRole = strtolower($matches[1]);
            }

            // Check if this is an IP lock (contains dots and no valid role)
            $isIpLock = (strpos($identifier, '.') !== false && ! in_array($lockoutRole, self::ALLOWED_ROLES));

            if ($isIpLock) {
                $key = self::LOGIN_KEY_PREFIX.'ip|'.$identifier;
            } else {
                $key = self::LOGIN_KEY_PREFIX.strtolower($identifier).'|'.$lockoutRole;
            }

            // Check if this account is currently locked via its lock record.
            $remaining = LoginAttemptGuard::remainingLock($key);

            if ($remaining !== null && $remaining > 0) {
                $lockedAccounts[] = [
                    'identifier' => $identifier,
                    'role' => $isIpLock ? 'ip' : $lockoutRole,
                    'lockedAt' => $lockout->createdAt,
                    'unlockAt' => now()->addSeconds($remaining)->toIso8601String(),
                    'remainingSeconds' => $remaining,
                ];
            }
        }

        // Manual IP blocks placed by an admin (persist until unlocked).
        $manualLocks = AuditLog::query()
            ->where('action', 'ip_manual_lock')
            ->where('createdAt', '>=', now()->subDays(30))
            ->orderByDesc('createdAt')
            ->get();

        foreach ($manualLocks as $lock) {
            $ip = $lock->recordId;

            if (! LoginAttemptGuard::isIpLocked($ip)) {
                continue;
            }

            $lockedAccounts[] = [
                'identifier' => $ip,
                'role' => 'ip',
                'lockedAt' => $lock->createdAt,
                'unlockAt' => null,
                'remainingSeconds' => PHP_INT_MAX,
                'manual' => true,
            ];
        }

        // Remove duplicates and sort by remaining time (shortest first);
        // manual blocks without an expiry sort to the bottom.
        $uniqueAccounts = [];
        $seen = [];
        foreach ($lockedAccounts as $account) {
            $key = $account['identifier'].'|'.$account['role'].'|'.($account['manual'] ?? false ? 'manual' : 'auto');
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueAccounts[] = $account;
            }
        }

        usort($uniqueAccounts, fn ($a, $b) => $a['remainingSeconds'] <=> $b['remainingSeconds']);

        return $uniqueAccounts;
    }
}
