<?php

use App\Http\Middleware\BlockManuallyLockedIp;
use App\Http\Middleware\DecryptRouteParameters;
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
        // Host header validation is enabled here, but the concrete host list is
        // applied in AppServiceProvider once configuration is loaded. This
        // callback can run before the environment is bootstrapped, so reading
        // env()/config() here would see empty values during config caching.
        $middleware->trustHosts();

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'password.updated' => EnsurePasswordUpdated::class,
            'student.enrolled' => EnsureStudentEnrollment::class,
            'manual-ip-block' => BlockManuallyLockedIp::class,
        ]);

        // Runs first so route model binding, controllers, policies and the
        // view closures that echo an id all receive the plaintext identifier.
        // Unmarked values pass through, so links minted before the cipher
        // existed keep working.
        $middleware->prependToGroup('web', DecryptRouteParameters::class);

        $middleware->web(append: [
            TrackActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
