# Relayhub — Complete Project (Backend + Livewire Frontend)

This zip contains your **entire `app/` and `routes/` backend** (unchanged from
your JWT/API version) PLUS the new **Livewire + Blade frontend** that replaces
your React app.

---

## 1. Create a fresh Laravel project in Herd

```bash
composer create-project laravel/laravel relayhub
cd relayhub
```

---

## 2. Install required packages (matches your old composer.json)

```bash
composer require livewire/livewire
composer require php-open-source-saver/jwt-auth
composer require spatie/laravel-permission
composer require laravel/reverb
composer require laravel/sanctum
composer require laravel/fortify
```

Publish configs:
```bash
php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan reverb:install
```

---

## 3. Copy this zip's contents into your project

```
app/Events/MessageSent.php          → app/Events/
app/Events/RoleChanged.php          → app/Events/

app/Http/Controllers/*.php          → app/Http/Controllers/
app/Http/Middleware/*.php           → app/Http/Middleware/
app/Livewire/*.php                  → app/Livewire/   (NEW folder — matches your starter kit)

app/Models/User.php                 → app/Models/  (replace default)
app/Models/Conversation.php         → app/Models/
app/Models/Message.php              → app/Models/

app/Policies/*.php                  → app/Policies/

app/Providers/AppServiceProvider.php         → app/Providers/ (replace)
app/Providers/BroadcastServiceProvider.php   → app/Providers/ (replace)
app/Providers/FortifyServiceProvider.php     → app/Providers/ (replace)

app/helpers.php                     → app/helpers.php  (NEW)

routes/api.php                      → routes/api.php   (replace)
routes/channels.php                 → routes/channels.php (replace)
routes/console.php                  → routes/console.php (replace)
routes/web.php                      → routes/web.php   (replace)

resources/views/layouts/*           → resources/views/layouts/   (NEW)
resources/views/components/*        → resources/views/components/ (NEW)
resources/views/livewire/*          → resources/views/livewire/  (NEW)

resources/css/app.css               → resources/css/app.css (replace)
resources/js/app.js                 → resources/js/app.js   (replace)
```

---

## 4. composer.json — register helpers.php

```json
"autoload": {
    "psr-4": { "App\\": "app/" },
    "files": ["app/helpers.php"]
}
```
```bash
composer dump-autoload
```

---

## 5. Register providers (bootstrap/providers.php on Laravel 11+)

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
];
```

---

## 6. Register middleware aliases (bootstrap/app.php)

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
    ]);
})
```

---

## 7. config/auth.php — add 'api' guard for JWT (still used by your mobile/API clients)

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

---

## 8. Database

```bash
php artisan migrate
```

You'll need migrations for:
- `users` (add: `bio`, `avatar`, `storage_used`, `storage_quota`, two-factor columns)
- `conversations` (`user_one_id`, `user_two_id`, `last_message_at`)
- `messages` (`conversation_id`, `sender_id`, `body`, `reply_to_id`, `read_at`, `file_path`, `file_name`, `file_type`, `file_size`)
- Spatie permission tables (`php artisan migrate` after publishing Spatie migrations)

Seed roles:
```php
Spatie\Permission\Models\Role::create(['name' => 'user']);
Spatie\Permission\Models\Role::create(['name' => 'admin']);
```

---

## 9. .env essentials

```env
APP_URL=http://relayhub.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=relayhub
DB_USERNAME=postgres
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_DOMAIN=.relayhub.test

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=relayhub
REVERB_APP_KEY=relayhubkey
REVERB_APP_SECRET=relayhubsecret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

JWT_SECRET=  # php artisan jwt:secret
```

---

## 10. Storage link

```bash
php artisan storage:link
```

---

## 11. Run everything (3 terminals)

```bash
npm install && npm run dev
php artisan reverb:start
php artisan queue:work
```

Visit **http://relayhub.test** → Login/Register → Chat works exactly like
your React app, including: real-time messages, presence (online/offline),
file uploads, message search, reply, profile editing + avatar upload,
storage quota bar, and admin user management.

---

## Notes

- `routes/api.php` is **untouched** — your JWT API still works for any
  external/mobile clients.
- `routes/web.php` is **new** — replaces the Inertia routes, serves the
  Livewire chat app.
- `FortifyServiceProvider` was modified to redirect login/register views to
  the new Livewire routes instead of Inertia pages.
- `BroadcastServiceProvider` was modified to use `web`+`auth` (session)
  middleware instead of `api` (JWT) — required because Livewire uses
  session auth, not JWT tokens.
