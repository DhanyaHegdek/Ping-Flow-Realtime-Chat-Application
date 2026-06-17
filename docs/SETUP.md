# Setup Guide

Full installation walkthrough for Pingflow, including required packages, file placement, provider/middleware registration, environment variables, and database schema.

## Table of Contents

- [1. Create or Clone the Project](#1-create-or-clone-the-project)
- [2. Install Required Packages](#2-install-required-packages)
- [3. Project File Layout](#3-project-file-layout)
- [4. Register `helpers.php`](#4-register-helpersphp)
- [5. Register Providers](#5-register-providers)
- [6. Register Middleware Alias](#6-register-middleware-alias)
- [7. Configure Guards](#7-configure-guards)
- [8. Run Migrations](#8-run-migrations)
- [9. Storage Symlink](#9-storage-symlink)
- [Environment Variables](#environment-variables)
- [Running the App](#running-the-app)
- [Creating an Admin User](#creating-an-admin-user)
- [Database Schema](#database-schema)
- [Implementation Notes](#implementation-notes)

---

## 1. Create or Clone the Project

```bash
composer create-project laravel/laravel pingflow
cd pingflow
```

Or, if cloning this repo directly:

```bash
git clone <your-repo-url>
cd pingflow
composer install
npm install
```

## 2. Install Required Packages

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

## 3. Project File Layout

If assembling this from separate backend/frontend sources rather than cloning a single repo, files map as follows:

```
app/Events/MessageSent.php          → app/Events/
app/Events/RoleChanged.php          → app/Events/

app/Http/Controllers/*.php          → app/Http/Controllers/
app/Http/Middleware/*.php           → app/Http/Middleware/
app/Livewire/*.php                  → app/Livewire/

app/Models/User.php                 → app/Models/  (replace default)
app/Models/Conversation.php         → app/Models/
app/Models/Message.php              → app/Models/

app/Policies/*.php                  → app/Policies/

app/Providers/AppServiceProvider.php         → app/Providers/ (replace)
app/Providers/BroadcastServiceProvider.php   → app/Providers/ (replace)
app/Providers/FortifyServiceProvider.php     → app/Providers/ (replace)

app/helpers.php                     → app/helpers.php

routes/api.php                      → routes/api.php   (replace)
routes/channels.php                 → routes/channels.php (replace)
routes/console.php                  → routes/console.php (replace)
routes/web.php                      → routes/web.php   (replace)

resources/views/layouts/*           → resources/views/layouts/
resources/views/components/*        → resources/views/components/
resources/views/livewire/*          → resources/views/livewire/

resources/css/app.css               → resources/css/app.css (replace)
resources/js/app.js                 → resources/js/app.js   (replace)
```

## 4. Register `helpers.php`

In `composer.json`:

```json
"autoload": {
    "psr-4": { "App\\": "app/" },
    "files": ["app/helpers.php"]
}
```

```bash
composer dump-autoload
```

## 5. Register Providers

`bootstrap/providers.php` (Laravel 11+):

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
];
```

## 6. Register Middleware Alias

`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
    ]);
})
```

## 7. Configure Guards

`config/auth.php`:

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

## 8. Run Migrations

```bash
php artisan migrate
```

Migrations needed (see [Database Schema](#database-schema) for full column details):

- `users` — additional columns: `bio`, `avatar`, `storage_used`, `storage_quota`
- `conversations` — `user_one_id`, `user_two_id`, `last_message_at`
- `messages` — `conversation_id`, `sender_id`, `body`, `reply_to_id`, `read_at`, `file_path`, `file_name`, `file_type`, `file_size`
- Spatie permission tables (created automatically after publishing Spatie's migration in step 2)

## 9. Storage Symlink

```bash
php artisan storage:link
```

---

## Environment Variables

```env
APP_URL=http://pingflow.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pingflow
DB_USERNAME=postgres
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_DOMAIN=.pingflow.test

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=pingflow
REVERB_APP_KEY=pingflowkey
REVERB_APP_SECRET=pingflowsecret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

JWT_SECRET=   # filled by php artisan jwt:secret
```

---

## Running the App

Three terminals, run concurrently:

```bash
npm run dev               # Vite — compiles CSS/JS
php artisan reverb:start  # WebSocket server
php artisan queue:work    # processes broadcast jobs (skip if QUEUE_CONNECTION=sync)
```

Visit `http://pingflow.test` → Register/Login → the chat works end to end: real-time messages, online/offline presence, file uploads, message search, reply threading, profile editing with avatar upload, storage quota bar, and admin user management.

---

## Creating an Admin User

Roles must exist before they can be assigned:

```bash
php artisan tinker
```

```php
Spatie\Permission\Models\Role::create(['name' => 'user']);
Spatie\Permission\Models\Role::create(['name' => 'admin']);
Spatie\Permission\Models\Role::create(['name' => 'super_admin']);

$user = App\Models\User::where('email', 'you@example.com')->first();
$user->syncRoles(['admin']);
```

Alternatively, create a seeder (`php artisan make:seeder AdminSeeder`) that creates the roles and a default admin account, then run `php artisan db:seed --class=AdminSeeder`.

---

## Database Schema

### `users` (extended from default Laravel)

| Column | Type | Notes |
|---|---|---|
| `bio` | text, nullable | |
| `avatar` | string, nullable | path under `storage/app/public/avatars` |
| `storage_used` | unsigned bigint, default 0 | bytes |
| `storage_quota` | unsigned bigint, default 1073741824 | bytes (1GB default) |

Plus Spatie's `model_has_roles`, `roles`, `permissions` tables.

### `conversations`

| Column | Type |
|---|---|
| `user_one_id` | foreign key → users |
| `user_two_id` | foreign key → users |
| `last_message_at` | timestamp, nullable |

### `messages`

| Column | Type |
|---|---|
| `conversation_id` | foreign key → conversations |
| `sender_id` | foreign key → users |
| `body` | text, nullable |
| `reply_to_id` | foreign key → messages, nullable |
| `read_at` | timestamp, nullable |
| `file_path` | string, nullable |
| `file_name` | string, nullable |
| `file_type` | string, nullable |
| `file_size` | unsigned bigint, nullable |

---

## Implementation Notes

- `routes/api.php` is untouched — the original JWT API still works for any external/mobile clients.
- `routes/web.php` is new — replaces the Inertia routes, serves the Livewire chat app.
- `FortifyServiceProvider` was modified to redirect login/register views to the new Livewire routes instead of Inertia pages.
- `BroadcastServiceProvider` was modified to use `web`+`auth` (session) middleware instead of `api` (JWT) — required because Livewire uses session auth, not JWT tokens.

See [ARCHITECTURE.md](ARCHITECTURE.md) for the reasoning behind these design decisions and a full file-by-file breakdown.
