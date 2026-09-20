<?php

namespace App\Providers;

use App\Services\PortalDataService;
use App\Support\LoginAttemptGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PortalDataService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinutes(LoginAttemptGuard::LOCK_MINUTES, LoginAttemptGuard::MAX_ATTEMPTS)
                ->by(LoginAttemptGuard::keyFor($request))
                ->response(function (Request $request, array $headers) {
                    return LoginAttemptGuard::blocked($request, LoginAttemptGuard::keyFor($request), $headers);
                });
        });

        RateLimiter::for('uploads', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->user_id ?? $request->ip());
        });

        RateLimiter::for('classroom-joins', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->user_id ?? $request->ip());
        });

        RateLimiter::for('account-requests', function (Request $request): Limit {
            return Limit::perMinute(2)->by($request->ip());
        });

        View::composer(
            [
                'admin.*',
                'student.*',
                'teacher.*',
                'parent.*',
                'guest.*',
            ],
            function ($view) {
                if (! Auth::check()) {
                    return;
                }

                static $boot;

                if ($boot === null) {
                    $boot = app(PortalDataService::class)->bootPayload(Auth::user());
                }

                $view->with('portalBoot', $boot);
            }
        );
    }
}
