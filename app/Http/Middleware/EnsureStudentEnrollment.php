<?php

namespace App\Http\Middleware;

use App\Models\Enrollment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentEnrollment
{
    /**
     * Student route names (page + API) that require an active "Enrolled"
     * enrollment. Matched as prefixes so resource names are covered too.
     *
     * @var array<int, string>
     */
    private const BLOCKED_ROUTE_PREFIXES = [
        'student.attendance',
        'student.documents',
        'student.competencies',
        'student.requirements',
        'student.grades',
        'student.classrooms',
        'student.api.classrooms',
        'student.api.activities',
        'student.document-requests',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'student') {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        $blocked = false;
        foreach (self::BLOCKED_ROUTE_PREFIXES as $prefix) {
            if (Str::startsWith($routeName, $prefix)) {
                $blocked = true;
                break;
            }
        }

        if (! $blocked || Enrollment::isStudentEnrolled($user->user_id)) {
            return $next($request);
        }

        $message = 'Enrollment is required to access this page.';

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()->route('student.dashboard')->withErrors([
            'enrollment' => $message,
        ]);
    }
}