# Pingflow

A real-time one-to-one chat application built with Laravel, Livewire, Alpine.js, and Laravel Reverb.

## Features

- Real-time one-to-one messaging with online/offline presence
- Reply-to-message threading
- File and image attachments with per-user storage quota
- In-conversation message search
- Toast notifications with unread badges
- Editable profile with avatar upload
- Admin panel — manage users, roles, and permissions (Spatie)
- Real-time role-change propagation

## Tech Stack

Laravel 13 · Livewire 3 · Alpine.js · Laravel Reverb (WebSockets) · Spatie Permission · PostgreSQL · Vite

## Quick Start

```bash
git clone <your-repo-url>
cd pingflow

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Fill in your database and Reverb credentials in `.env` (see [docs/SETUP.md](docs/SETUP.md) for the full reference), then:

```bash
php artisan migrate
php artisan storage:link
```

Run these three in separate terminals:

```bash
npm run dev
php artisan reverb:start
php artisan queue:work
```

Visit `http://pingflow.test`.

## Documentation

- **[docs/SETUP.md](docs/SETUP.md)** — full installation walkthrough, package list, provider/middleware registration, environment variables, database schema
- **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** — project structure, file-by-file explanation of backend and frontend, real-time system internals, authentication design

