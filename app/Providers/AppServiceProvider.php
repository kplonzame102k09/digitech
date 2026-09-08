<?php

namespace App\Providers;

use App\Services\PortalDataService;
use Illuminate\Support\Facades\Auth;
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
        View::composer(
            [
                'admin.*',
                'student.*',
                'teacher.*',
                'parent.*',
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
