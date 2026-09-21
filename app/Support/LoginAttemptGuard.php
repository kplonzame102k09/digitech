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
     * Cache key for a login attempt. Tracks the target account (lowercased
     * user id/email + role); malformed payloads fall back to the IP so
     * bots that skip the form still get throttled.
     */
    public static function keyFor(Request $request): string
    {
        $role = (string) $request->string('role');

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            return 'login:ip|'.$request->ip();
        }

        $identifier = Str::lower(trim((string) $request->string('user_id')));

        if ($identifier === '') {
            return 'login:ip|'.$request->ip();
        }

        return 'login:'.$identifier.'|'.$role;
    }

    /**
     * Drop the counter after a successful login so only genuine failures
     * accumulate toward the lock threshold.
     */
    public static function clearAttempts(Request $request): void
    {
        RateLimiter::clear(self::keyFor($request));
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
     * Lift the IP-fallback lock so a throttled source can submit again.
     *
     * @return string[] cleared cache/limiter keys
     */
    public static function unlockIp(?string $ip): array
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return [];
        }

        return self::clearKey('login:ip|'.$ip);
    }

    /**
     * Clear one rate-limiter key and its one-shot alert flag.
     *
     * @return string[] the keys that were cleared
     */
    protected static function clearKey(string $key): array
    {
        RateLimiter::clear($key);
        Cache::forget($key.':alerted');

        return [$key, $key.':alerted'];
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
            Cache::put($key.':alerted', true, now()->addMinutes(self::LOCK_MINUTES));

            $attempts = self::MAX_ATTEMPTS + 1;
            $lockedUntil = now()->addMinutes(self::LOCK_MINUTES)->format('g:i A \o\n M j, Y');

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