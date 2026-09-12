<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordUpdated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustChangePassword) {
            // The account/password call is how a flagged account rotates its
            // password, so it must stay reachable even inside api/portal.
            $exempt = $request->routeIs('auth.password.*')
                || $request->routeIs('account.password');

            if (! $exempt) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'You must change your password before continuing.',
                    ], 403);
                }

                return redirect()->route('auth.password.change');
            }
        }

        return $next($request);
    }
}
