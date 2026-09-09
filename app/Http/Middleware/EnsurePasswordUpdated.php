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

        if ($user && $user->mustChangePassword && ! $request->routeIs('auth.password.*')) {
            return redirect()->route('auth.password.change');
        }

        return $next($request);
    }
}
