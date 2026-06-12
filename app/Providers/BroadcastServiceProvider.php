<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * CHANGED: middleware switched from ['api'] (JWT) to ['web', 'auth']
     * because Livewire uses session-based auth, not JWT tokens.
     *
     * The existing /api/broadcasting/auth route in api.php still works
     * for any external JWT clients — this only affects the web Blade app.
     */
    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['web', 'auth']]);

        require base_path('routes/channels.php');
    }
}
