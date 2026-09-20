<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackActivity
{
    /**
     * Low-value request targets that are never recorded so the live stream
     * stays meaningful (assets, background sync polling, and the monitor's
     * own poll endpoint).
     *
     * @var array<int, string>
     */
    private const SKIPPED_PREFIXES = [
        '/api/portal/',
        '/admin/api/monitor/',
        '/storage/',
        '/images/',
        '/css/',
        '/js/',
        '/vendor/',
        '/build/',
        '/up',
        '/setup_seed.php',
        '/favicon.ico',
    ];

    /**
     * request methods that are never recorded.
     *
     * @var array<int, string>
     */
    private const SKIPPED_METHODS = ['HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! $this->shouldRecord($request)) {
                return $response;
            }

            $user = $request->user();
            $kind = $this->classifyKind($request, $response, $user);

            ActivityLog::record([
                'user_id' => $user?->getKey(),
                'role' => $this->resolveRoleColumn($request, $user, $kind),
                'method' => $request->method(),
                'path' => $this->trimPath($request->path()),
                'ip_address' => $this->clientIp($request),
                'user_agent' => $this->trimPath($request->userAgent() ?? ''),
                'status' => $response->getStatusCode(),
                'kind' => $kind,
                'context' => $this->buildContext($request),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the request.
        }

        return $response;
    }

    protected function shouldRecord(Request $request): bool
    {
        if (in_array($request->method(), self::SKIPPED_METHODS, true)) {
            return false;
        }

        $path = $request->path();

        foreach (self::SKIPPED_PREFIXES as $prefix) {
            if (str_starts_with('/'.$path, $prefix)) {
                return false;
            }
        }

        // Any static asset by extension, e.g. /js/app.js?v=123.
        if (preg_match('/\.[a-z0-9]{2,5}(\?.*)?$/i', $path) === 1) {
            return false;
        }

        return true;
    }

    /**
     * The visitor's real IP: prefer Cloudflare's CF-Connecting-IP when the
     * site is fronted by Cloudflare (it overwrites the header on origin,
     * so it cannot be spoofed by the client), otherwise fall back to the
     * proxy-trusted $request->ip() (X-Forwarded-For / REMOTE_ADDR).
     */
    protected function clientIp(Request $request): string
    {
        $cfIp = trim((string) $request->header('CF-Connecting-IP', ''));

        if (filter_var($cfIp, FILTER_VALIDATE_IP) !== false) {
            return $cfIp;
        }

        return (string) $request->ip();
    }

    /**
     * Tag the row with a security event kind so the monitoring page can
     * surface failures/blocks distinctly from ordinary traffic.
     */
    protected function classifyKind(Request $request, Response $response, $user): ?string
    {
        $status = $response->getStatusCode();
        $isLoginSubmit = $request->path() === 'login' && $request->method() === 'POST';

        if ($isLoginSubmit && $status === 429) {
            return 'login_blocked';
        }

        if ($status === 429) {
            return 'blocked';
        }

        if (! $isLoginSubmit) {
            return null;
        }

        if ($user !== null) {
            return 'login_success';
        }

        return in_array($status, [302, 422], true) ? 'login_failed' : null;
    }

    /**
     * When a login fails we have no authenticated user, so fall back to the
     * role the attacker was attempting to sign in as — this keeps the role
     * filter useful against targeted credential attacks.
     */
    protected function resolveRoleColumn(Request $request, $user, ?string $kind): ?string
    {
        if ($user !== null) {
            return $user->role;
        }

        if ($kind === 'login_failed' || $kind === 'login_blocked') {
            $role = trim((string) $request->input('role', ''));
            return $role !== '' ? $role : null;
        }

        return null;
    }

    /**
     * Compact JSON of the attempted target (role + typed user id) for
     * failed/blocked sign-in attempts. Null for ordinary traffic.
     */
    protected function buildContext(Request $request): ?string
    {
        if ($request->path() !== 'login' || $request->method() !== 'POST') {
            return null;
        }

        $role = trim((string) $request->input('role', ''));
        $target = trim((string) $request->input('user_id', ''));

        $context = [];
        if ($role !== '') {
            $context['role'] = $role;
        }
        if ($target !== '') {
            $context['target'] = $target;
        }

        return $context === [] ? null : json_encode($context);
    }

    protected function trimPath(?string $value): string
    {
        $value ??= '';

        return mb_strlen($value) > 500 ? mb_substr($value, 0, 500) : $value;
    }
}