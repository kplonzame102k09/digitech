<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\LoginAttemptGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    /** Session is considered "online" when seen within this many seconds. */
    private const ONLINE_WINDOW = 300;

    /** Bound on rows returned per poll so the page stays cheap to refresh. */
    private const ACTIVITY_LIMIT = 100;

    private const ALLOWED_ROLES = ['student', 'teacher', 'admin', 'parent', 'guest'];

    public function index()
    {
        return view('admin.live-monitoring', [
            'title' => 'Live Monitoring',
            'subtitle' => 'Realtime portal traffic and activity',
        ]);
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

        return response()->json([
            'ok' => true,
            'now' => now()->toIso8601String(),
            'activity' => $activity,
            'online' => $online,
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

    protected function onlineSessions(): array
    {
        $cutoff = now()->subSeconds(self::ONLINE_WINDOW)->getTimestamp();

        return \Illuminate\Support\Facades\DB::table('sessions')
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
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
            ])
            ->map(function ($row): array {
                return [
                    'userId' => $row->user_id,
                    'name' => trim($row->firstName.' '.$row->lastName),
                    'role' => $row->role,
                    'ip' => $row->ip_address,
                    'ua' => $row->user_agent,
                    'lastActivity' => $row->last_activity,
                    'ago' => now()->diffInSeconds(now()->createFromTimestamp($row->last_activity)),
                ];
            })
            ->all();
    }

    protected function stats(int $window, int $onlineNow): array
    {
        $activeHourCutoff = now()->subHour()->getTimestamp();
        $onlineSessions = \Illuminate\Support\Facades\DB::table('sessions')
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
}