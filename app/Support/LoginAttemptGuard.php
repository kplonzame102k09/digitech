<?php

namespace App\Support;

use App\Mail\AdminSecurityAlert;
use App\Mail\LoginAttemptAlert;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardened sign-in throttling: at most 3 failed attempts for one account
 * (per role), then the account is locked for 15 minutes. On lock the account
 * owner and every active admin are alerted (email + in-app notification) and
 * the event is recorded in the audit log. Applies to every portal role since
 * all roles share the same login route.
 */
class LoginAttemptGuard
{
    public const MAX_ATTEMPTS = 3;

    public const LOCK_MINUTES = 15;

    private const ALLOWED_ROLES = ['student', 'teacher', 'admin', 'parent', 'guest'];

    /**
     * Shared throttle key for malformed login payloads (invalid role or empty
     * user id). These are almost always bots, so they are slowed globally
     * instead of locking a real IP — IP blocks are applied manually by admins.
     */
    public const ANON_KEY = 'login:anon';

    /**
     * Cache prefix for IPs blocked manually by an admin. Blocks persist until
     * an admin unlocks them (no TTL).
     */
    public const MANUAL_LOCK_PREFIX = 'manual-ip-lock:';

    /**
     * Cache prefix for explicit lock records written when a sign-in lock
     * fires. Used by the admin monitor to list still-locked accounts without
     * depending on the (hashed) rate-limiter keys.
     */
    public const LOCK_META_PREFIX = 'login:locked:';

    /**
     * Cache key for a login attempt. Tracks the target account (lowercased
     * user id/email + role). The login form does not submit a role field, so
     * the role is derived from the user id prefix (mirroring the login
     * controller); requests that explicitly carry an invalid role or an empty
     * user id fall back to a shared anonymous key so bots are still throttled
     * without locking an IP (IP blocks are created manually by admins).
     */
    public static function keyFor(Request $request): string
    {
        $identifier = Str::lower(trim((string) $request->string('user_id')));

        if ($identifier === '') {
            return self::ANON_KEY;
        }

        $role = (string) $request->string('role');

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            $role = self::roleForUserId($identifier);
        }

        return 'login:'.$identifier.'|'.$role;
    }

    /**
     * Determine the role from a user id prefix (ADMIN-, STU-, TCH-, PRT-,
     * GST-). Mirrors the logic that authenticates the account so throttle
     * keys match real sign-in attempts.
     */
    public static function roleForUserId(string $userId): string
    {
        $userIdUpper = strtoupper($userId);

        if (str_starts_with($userIdUpper, 'ADMIN')) {
            return 'admin';
        }

        $prefix = substr($userIdUpper, 0, 3);

        return match ($prefix) {
            'STU' => 'student',
            'TCH' => 'teacher',
            'PRT' => 'parent',
            'GST' => 'guest',
            default => 'student',
        };
    }

    /**
     * Seconds remaining in the lock window for a given throttle key, or null
     * when the account is not currently locked.
     */
    public static function remainingLock(string $key): ?int
    {
        $unlockAt = Cache::get(self::LOCK_META_PREFIX.$key);

        if (! is_string($unlockAt) || $unlockAt === '') {
            return null;
        }

        $remaining = (int) (strtotime($unlockAt) - time());

        return $remaining > 0 ? $remaining : null;
    }

    /**
     * Drop the counter after a successful login so only genuine failures
     * accumulate toward the lock threshold. Clears both the plain key and the
     * md5-hashed limiter key the throttle middleware actually stores under.
     */
    public static function clearAttempts(Request $request): void
    {
        $key = self::keyFor($request);
        RateLimiter::clear($key);
        Cache::forget(self::hashedLimiterKey($key));
        Cache::forget(self::hashedLimiterKey($key).':timer');
    }

    /**
     * Lift a per-account sign-in lock so the user can try again immediately.
     * Resolves the real account (user_id or email, role-matched) and clears
     * every lock key that could match what the attacker typed, plus the
     * one-shot alert flag so a future lock re-alerts as a fresh event.
     *
     * @return string[] cleared cache/limiter keys
     */
    public static function unlockAccount(?string $identifier, string $role): array
    {
        $identifier = Str::lower(trim((string) $identifier));
        $cleared = [];

        if ($identifier !== '') {
            $cleared = array_merge($cleared, self::clearKey('login:'.$identifier.'|'.$role));
        }

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            return $cleared;
        }

        $user = User::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('user_id', $identifier)
                    ->orWhereRaw('LOWER(email) = ?', [$identifier]);
            })
            ->where('role', $role)
            ->where('status', 'active')
            ->first();

        if (! $user) {
            return $cleared;
        }

        foreach (array_unique(array_filter([
            Str::lower((string) $user->user_id),
            Str::lower((string) $user->email),
        ], fn ($value): bool => $value !== '')) as $candidate) {
            $cleared = array_merge($cleared, self::clearKey('login:'.$candidate.'|'.$role));
        }

        return array_values(array_unique($cleared));
    }

    /**
     * Lift the IP-block so a blocked source can submit again. Clears both the
     * legacy limiter key (auto IP throttle from older builds) and any manual
     * IP block placed by an admin.
     *
     * @return string[] cleared cache/limiter keys
     */
    public static function unlockIp(?string $ip): array
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return [];
        }

        $cleared = self::clearKey('login:ip|'.$ip);
        $manual = self::manualLockKey($ip);
        Cache::forget($manual);

        return array_values(array_unique(array_merge($cleared, [$manual])));
    }

    /**
     * Block an IP until an admin manually unlocks it. The lock is stored with
     * no TTL so it persists across requests until it is removed.
     */
    public static function lockIp(string $ip, ?string $by = null): void
    {
        $ip = trim($ip);

        if ($ip === '') {
            return;
        }

        Cache::put(self::manualLockKey($ip), [
            'lockedAt' => now()->toIso8601String(),
            'by' => $by,
        ]);
    }

    /**
     * Whether an IP has been manually blocked by an admin.
     */
    public static function isIpLocked(?string $ip): bool
    {
        return is_string($ip) && $ip !== '' && Cache::has(self::manualLockKey($ip));
    }

    protected static function manualLockKey(string $ip): string
    {
        return self::MANUAL_LOCK_PREFIX.$ip;
    }

    /**
     * Clear one rate-limiter key (plain + hashed), its one-shot alert flag
     * and its explicit lock record.
     *
     * @return string[] the keys that were cleared
     */
    protected static function clearKey(string $key): array
    {
        RateLimiter::clear($key);
        Cache::forget($key.':alerted');
        Cache::forget(self::LOCK_META_PREFIX.$key);

        $hashed = self::hashedLimiterKey($key);
        Cache::forget($hashed);
        Cache::forget($hashed.':timer');

        return [$key, $key.':alerted', self::LOCK_META_PREFIX.$key, $hashed, $hashed.':timer'];
    }

    /**
     * The key the throttle middleware actually stores limiter hits under:
     * md5(limiterName.limitKey), with "login" as the named limiter.
     */
    protected static function hashedLimiterKey(string $key): string
    {
        return md5('login'.$key);
    }

    /**
     * Response builder for the moment the rate limit is crossed: fire the
     * security alerts exactly once per lock window, record the audit entry,
     * then answer with a friendly message instead of a bare 429 page.
     */
    public static function blocked(Request $request, string $key, array $headers = []): Response
    {
        [$identifier, $role] = self::resolveAttempt($key);

        $owner = ($identifier !== null && $role !== null)
            ? self::resolveOwner($identifier, $role)
            : null;

        if (! Cache::has($key.':alerted')) {
            $unlockAt = now()->addMinutes(self::LOCK_MINUTES);

            Cache::put($key.':alerted', true, $unlockAt);
            Cache::put(self::LOCK_META_PREFIX.$key, $unlockAt->toIso8601String(), $unlockAt);

            $attempts = self::MAX_ATTEMPTS + 1;
            $lockedUntil = $unlockAt->format('g:i A \o\n M j, Y');

            if ($owner) {
                self::alertAccountOwner($request, $owner, $identifier, $role, $attempts, $lockedUntil);
            }

            self::alertAdmins($request, $identifier, $role, $attempts, $lockedUntil);

            AuditLog::record([
                'entity' => AuditLog::ENTITY_USER,
                'recordId' => $identifier ?? $request->ip(),
                'action' => 'login_lockout',
                'notes' => 'Brute-force sign-in attempt blocked for '
                    .($role ?? 'unknown').' account '.($identifier ?? $request->ip())
                    .' from '.$request->ip().'. Sign-in locked for '.self::LOCK_MINUTES.' minutes.',
                'reason' => 'More than '.self::MAX_ATTEMPTS.' failed sign-in attempts',
                'actorId' => $owner ? $owner->user_id : (User::query()->where('role', 'admin')->where('status', 'active')->value('user_id') ?? null),
            ]);
        }

        $message = 'Too many failed sign-in attempts. This account is locked for '.self::LOCK_MINUTES.' minutes. Please try again later.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => false,
                'error' => $message,
                'retryAfter' => self::LOCK_MINUTES * 60,
            ], 429, ['Retry-After' => self::LOCK_MINUTES * 60]);
        }

        return back()->withInput()->withErrors(['user_id' => $message]);
    }

    /**
     * Turns the normalized key back into the account identifier and role the
     * attacker typed. IP-fallbacks carry no real target and yield nulls.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected static function resolveAttempt(string $key): array
    {
        $parts = explode('|', substr($key, strlen('login:')), 2);

        $role = $parts[1] ?? '';

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            return [null, null];
        }

        return [$parts[0] ?? '', $role];
    }

    protected static function resolveOwner(string $identifier, string $role): ?User
    {
        return User::query()
            ->where(function ($query) use ($identifier): void {
                $query->whereRaw('LOWER(user_id) = ?', [$identifier])
                    ->orWhereRaw('LOWER(email) = ?', [$identifier]);
            })
            ->where('role', $role)
            ->where('status', 'active')
            ->first();
    }

    protected static function alertAccountOwner(Request $request, User $owner, string $identifier, string $role, int $attempts, string $lockedUntil): void
    {
        if (! is_string($owner->email) || trim($owner->email) === '') {
            return;
        }

        try {
            Mail::to($owner->email)->send(new LoginAttemptAlert(
                name: trim($owner->firstName.' '.$owner->lastName) ?: 'there',
                identifier: $identifier,
                role: $role,
                ip: $request->ip(),
                attempts: $attempts,
                lockedUntil: $lockedUntil,
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to send sign-in attempt alert to account owner', [
                'email' => $owner->email,
                'account' => $identifier,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function alertAdmins(Request $request, ?string $identifier, ?string $role, int $attempts, string $lockedUntil): void
    {
        $portal = app(PortalDataService::class);

        $identifierLabel = $identifier ?: '(unknown account)';
        $roleLabel = $role ?: 'unknown';

        // In-app notification for every admin. Security alerts always fire,
        // regardless of the "notify admins" preference toggle.
        $portal->notifyAdmins(
            'Brute-force sign-in attempt blocked',
            $attempts.' failed sign-in attempts for '.$roleLabel.' account '.$identifierLabel
                .' from '.$request->ip().'. Sign-in locked for '.self::LOCK_MINUTES.' minutes.',
            Notification::SOURCE_SECURITY,
            null,
            true,
        );

        foreach ($portal->adminRecipients() as $admin) {
            try {
                Mail::to($admin['email'])->send(new AdminSecurityAlert(
                    name: $admin['name'],
                    identifier: $identifierLabel,
                    role: $roleLabel,
                    ip: $request->ip(),
                    attempts: $attempts,
                    lockedUntil: $lockedUntil,
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to send admin security alert', [
                    'email' => $admin['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
