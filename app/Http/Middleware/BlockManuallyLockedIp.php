<?php

namespace App\Http\Middleware;

use App\Support\LoginAttemptGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject sign-in requests from an IP that an admin has manually blocked.
 * Runs before the login throttle so a blocked source cannot keep burning
 * attempts against the target account's limiter.
 */
class BlockManuallyLockedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (LoginAttemptGuard::isIpLocked($request->ip())) {
            $message = 'This IP address is blocked. Contact the administrator to regain access.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok' => false,
                    'error' => $message,
                ], 429);
            }

            return back()->withInput()->withErrors(['user_id' => $message]);
        }

        return $next($request);
    }
}
