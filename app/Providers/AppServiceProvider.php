<?php

namespace App\Providers;

use App\Services\PortalDataService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PortalDataService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::lower($request->string('user_id')->toString()).'|'.$request->ip());
        });

        RateLimiter::for('uploads', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->user_id ?? $request->ip());
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
