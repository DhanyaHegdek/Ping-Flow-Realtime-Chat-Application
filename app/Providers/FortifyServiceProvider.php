<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureRateLimiting();

        /*
        |------------------------------------------------------------------
        | Override Fortify views → point to our Livewire routes
        |
        | The original FortifyServiceProvider used Inertia::render().
        | Since we now use Livewire/Blade, we redirect Fortify's view
        | callbacks to our named routes instead.
        |------------------------------------------------------------------
        */

        // Login page → our Livewire login route
        Fortify::loginView(fn() => redirect()->route('login'));

        // Register page → our Livewire register route
        Fortify::registerView(fn() => redirect()->route('register'));

        // After login, go to chat
        Fortify::redirects('login', '/');
        Fortify::redirects('logout', '/login');
        Fortify::redirects('register', '/');
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())) . '|' . $request->ip()
            );
            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
