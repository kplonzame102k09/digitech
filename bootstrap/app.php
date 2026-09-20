<?php

use App\Http\Middleware\EnsurePasswordUpdated;
use App\Http\Middleware\EnsureStudentEnrollment;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\TrackActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // InfinityFree + any CDN/proxy in front send the visitor's real IP
        // via X-Forwarded-For; trust it so $request->ip() stays accurate for
        // activity logging, sessions, and login throttling.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'password.updated' => EnsurePasswordUpdated::class,
            'student.enrolled' => EnsureStudentEnrollment::class,
        ]);

        $middleware->web(append: [
            TrackActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
