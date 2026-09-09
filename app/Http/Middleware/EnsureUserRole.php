<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            $home = match ($user->role) {
                'admin' => '/admin/dashboard',
                'teacher' => '/teacher/dashboard',
                'student' => '/student/dashboard',
                'parent' => '/parent/dashboard',
                'guest' => '/guest/announcements',
                default => '/',
            };

            return redirect($home)->withErrors([
                'role' => 'You do not have access to that area.',
            ]);
        }

        return $next($request);
    }
}
