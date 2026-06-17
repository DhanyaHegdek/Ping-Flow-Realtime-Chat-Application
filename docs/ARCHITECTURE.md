# Architecture

This document covers the project structure, a file-by-file explanation of the backend and frontend, how the real-time system works internally, and the authentication design.

## Table of Contents

- [Tech Stack](#tech-stack)
- [Architecture Overview](#architecture-overview)
- [Project Structure](#project-structure)
- [Backend — File by File](#backend--file-by-file)
- [Frontend — File by File](#frontend--file-by-file)
- [Real-Time System Explained](#real-time-system-explained)
- [Authentication](#authentication)

---

## Tech Stack

| Layer                  | Technology                                                                        |
| ---------------------- | --------------------------------------------------------------------------------- |
| Backend framework      | Laravel 13                                                                        |
| Frontend rendering     | Blade + Livewire 3 (server-driven UI)                                             |
| Client-side reactivity | Alpine.js (bundled with Livewire)                                                 |
| Real-time transport    | Laravel Reverb (WebSocket server) + Laravel Echo (client)                         |
| Session auth           | Laravel `web` guard (cookie/session)                                              |
| API auth               | JWT via `php-open-source-saver/jwt-auth` (`api` guard, kept for external clients) |
| Roles & permissions    | Spatie `laravel-permission`                                                       |
| Database               | PostgreSQL                                                                        |
| File storage           | Laravel `Storage` facade → `storage/app/public` (symlinked)                       |
| Build tool             | Vite                                                                              |

---

## Architecture Overview

This project intentionally runs **two parallel auth systems**:

1. **Session-based (`web` guard)** — powers the Blade/Livewire chat UI that end users interact with in the browser.
2. **JWT (`api` guard)** — the original REST API (`routes/api.php`), kept fully intact for any external or mobile client that needs token-based auth.

Both guards authenticate against the same `User` model and the same database. A user could, in theory, be logged into the web app via session and also hold a valid JWT for API access — these are independent.

The Livewire frontend does **not** call the JWT API internally. It talks to the database directly through Eloquent models inside Livewire components, which is the standard Livewire pattern (no separate API layer needed for the UI it serves).

---

## Project Structure

```
app/
├── Events/
│   ├── MessageSent.php
│   └── RoleChanged.php
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── AdminController.php
│   │   ├── ChatController.php
│   │   ├── ProfileController.php
│   │   └── Controller.php
│   └── Middleware/
│       └── HandleAppearance.php
├── Livewire/
│   ├── Chat.php
│   ├── Login.php
│   ├── Register.php
│   ├── UsersPage.php
│   ├── ProfileTabs.php
│   └── EditProfilePanel.php
├── Models/
│   ├── User.php
│   ├── Conversation.php
│   └── Message.php
├── Policies/
│   ├── UserPolicy.php
│   └── RolePolicy.php
├── Providers/
│   ├── AppServiceProvider.php
│   ├── BroadcastServiceProvider.php
│   └── FortifyServiceProvider.php
└── helpers.php

resources/
├── views/
│   ├── layouts/
│   │   ├── app.blade.php
│   │   └── auth.blade.php
│   ├── components/
│   │   └── avatar.blade.php
│   └── livewire/
│       ├── chat.blade.php
│       ├── login.blade.php
│       ├── register.blade.php
│       ├── users-page.blade.php
│       ├── profile-tabs.blade.php
│       └── edit-profile-panel.blade.php
├── css/app.css
└── js/
    ├── app.js
    └── bootstrap.js

routes/
├── web.php       (Livewire routes — session auth)
├── api.php       (original JWT API — untouched)
└── channels.php  (broadcasting authorization)
```

---

## Backend — File by File

### `app/Events/MessageSent.php`

Fired every time a message is created (via Livewire or the upload controller). Implements `ShouldBroadcast` so Laravel pushes it to Reverb instead of only running in-process.

- **`__construct()`** eager-loads `sender` and `replyTo.sender` before broadcasting, so the WebSocket payload already contains the sender's name and avatar — no second query needed on the receiving end.
- **`broadcastOn()`** returns a `PresenceChannel` scoped to `conversation.{id}`. Presence (not Private) is used because the app also needs to know who is currently online in that conversation.
- **`broadcastWith()`** narrows the payload to `{ message: {...} }`, which is the exact shape the frontend JavaScript expects.

### `app/Events/RoleChanged.php`

Fired when an admin changes a user's role. Broadcasts on a `PrivateChannel('App.Models.User.{id}')` — only that specific user's browser can receive it. Carries `user_id` and `new_role`.

### `routes/channels.php`

Defines who is authorized to listen on each broadcast channel.

```php
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // returns false (rejected) unless $user belongs to that conversation
    // returns ['id' => ..., 'name' => ...] on success — this becomes
    // their entry in the presence channel's "who's online" roster
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

This file runs **server-side** on every channel subscription attempt — it is the actual security boundary, not just a convenience check.

### `app/Providers/BroadcastServiceProvider.php`

Registers the `/broadcasting/auth` endpoint that Echo calls before subscribing to any channel.

```php
Broadcast::routes(['middleware' => ['web', 'auth']]);
```

This was changed from the original `['api']` (JWT) middleware. Session cookies (not JWT bearer tokens) authenticate the browser, so the broadcasting auth check needs the `web` middleware group to read the session.

### `app/Http/Controllers/AuthController.php`

Unmodified from the original API. Still powers `/api/login`, `/api/register`, `/api/logout`, `/api/me` for JWT-based clients. Not called by the Livewire frontend.

### `app/Http/Controllers/ChatController.php`

The original REST endpoints for conversations/messages (`/api/conversations`, `/api/conversations/{id}/messages`, etc.). The Livewire frontend reimplements this same logic directly inside `app/Livewire/Chat.php` rather than calling these endpoints — except for **file uploads**, which still go through `ChatController::uploadFile()` via a direct `fetch()` call (see [Real-Time System Explained](#real-time-system-explained)).

### `app/Http/Controllers/AdminController.php`

Original JWT-protected admin endpoints (`/api/admin/users/*`). Logic mirrored inside `app/Livewire/UsersPage.php` for the web UI; this controller remains for API clients.

### `app/Http/Controllers/ProfileController.php`

Original JWT-protected profile endpoints. Logic mirrored inside `app/Livewire/EditProfilePanel.php`.

### `app/Models/User.php`

Extends `Authenticatable`, implements `JWTSubject` (for the API guard), uses `HasRoles` (Spatie). Notable custom methods:

```php
public function storageRemaining(): int
public function storagePercentage(): float
public function getStorageUsedFormattedAttribute(): string
public function getStorageQuotaFormattedAttribute(): string
```

These back the storage quota bar shown in the sidebar.

### `app/Models/Conversation.php`

`belongsTo` relationships to two users (`userOne`, `userTwo`), `hasMany` messages, and a `latestMessage()` relation (`hasOne(...)->latestOfMany()`) used for sidebar previews.

### `app/Models/Message.php`

`belongsTo` sender and conversation, self-referencing `replyTo`/`replies` relationships, an `isImage()` helper, and a `getFileSizeFormattedAttribute()` accessor used throughout the UI wherever a file size needs to be displayed.

### `app/Policies/UserPolicy.php` / `RolePolicy.php`

Spatie-permission-based authorization gates, scaffolded for fine-grained permission checks beyond simple role checks.

---

## Frontend — File by File

### `app/Livewire/Login.php` / `Register.php`

Session-based login and registration. Use `Auth::guard('web')->attempt()` / `Auth::guard('web')->login()` to authenticate via the session, rather than generating a JWT token. `Register.php` also calls `$user->assignRole('user')` to mirror `AuthController::register()`'s role-assignment behavior.

### `app/Livewire/Chat.php`

The core component. Properties hold all UI state (`activeConvId`, `conversations`, `messages`, `showNewChat`, etc.) — there is no client-side state management library; Livewire's public properties **are** the state, synced automatically between server and DOM.

Key methods:

| Method                       | Purpose                                                                                                           |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `mount()`                    | Runs once on page load — loads conversations and storage info                                                     |
| `loadConversations()`        | Queries conversations belonging to the current user, serializes for the view                                      |
| `selectConversation($id)`    | Switches the active conversation, loads its messages, resets search/profile state                                 |
| `loadMessages()`             | Fetches messages for the active conversation with sender/reply data                                               |
| `sendMessage()`              | Validates, creates the `Message` row, updates `last_message_at`, broadcasts `MessageSent`, appends to local state |
| `startConversation($userId)` | Finds or creates a conversation between the current user and another                                              |
| `searchMessages()`           | Case-insensitive (`ILIKE`) search scoped to the active conversation                                               |
| `logout()`                   | `Auth::guard('web')->logout()` + session invalidation                                                             |

Listeners registered on the component:

```php
protected $listeners = [
    'fileUploaded'          => 'loadMessages',
    'refreshMessages'       => 'loadMessages',
    'refreshConversations'  => 'loadConversations',
    'closeEditProfilePanel' => 'closeEditProfile',
    'profileUpdated'        => '$refresh',
];
```

These are triggered from JavaScript via `Livewire.dispatch('eventName')` — this is the bridge that lets the Alpine/Echo layer (browser-only state) tell the PHP component to re-run a query and re-render.

### `app/Livewire/UsersPage.php`

Admin user management. `createUser()`, `changeRole()`, and `deleteUser()` mirror `AdminController`'s logic exactly, with `changeRole()` additionally calling `broadcast(new RoleChanged(...))` so the affected user's browser is notified instantly.

### `app/Livewire/EditProfilePanel.php`

Nested Livewire component rendered inside the Chat view when a user clicks their own name/avatar. Uses the `WithFileUploads` trait — `updatedAvatarFile()` fires automatically the moment a file is selected (no submit button needed for the avatar specifically), deletes the old avatar from disk, and stores the new one.

### `app/Livewire/ProfileTabs.php`

Nested component shown in the right-hand profile panel when viewing the other person in a conversation. Two tabs: conversation info (message count, dates) and shared media (images/files grouped separately).

### `resources/views/livewire/chat.blade.php`

The largest view in the project. Structurally:

1. Toast notification container (Alpine-rendered)
2. Sidebar — brand, current user button, search, conversation list, admin link (conditional on role), storage bar
3. Message column — header with online status, search panel, message list, reply banner, input row
4. Right profile panel (conditional)
5. New conversation modal (conditional)
6. A `@push('scripts')` block containing the `chatApp()` Alpine component and two vanilla JS helper functions (`uploadFile`, `scrollToMessage`)

The Alpine `chatApp()` function is the client-side notification/presence engine — see [Real-Time System Explained](#real-time-system-explained) below for a full breakdown.

### `resources/views/components/avatar.blade.php`

Reusable Blade component. Generates initials and a deterministic HSL color from the user's name if no avatar image is set, otherwise renders the stored avatar image. Used everywhere a user avatar appears (sidebar, message bubbles, profile panels, admin table).

### `resources/js/app.js`

```js
import Echo from "laravel-echo";
import Pusher from "pusher-js";
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ["ws", "wss"],
});
```

Opens the actual WebSocket connection. `pusher-js` is used as the transport client because Reverb is wire-compatible with the Pusher protocol — `broadcaster: 'reverb'` just selects the right connector logic internally. No `authEndpoint`/`auth.headers` config is needed here because the browser automatically sends the session cookie with the `/broadcasting/auth` request — session-based auth handles this implicitly, unlike a token-based setup where the client would need to manually attach an `Authorization` header.

### `resources/js/bootstrap.js`

```js
import axios from "axios";
window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
```

Standard Laravel scaffolding — exposes `axios` globally and sets the header Laravel uses to detect AJAX requests.

---

## Real-Time System Explained

### End-to-end flow when User A sends a message to User B

```
User A types message → wire:model.defer="text" (no network call yet)
        ↓
User A presses Enter → $wire.sendMessage()
        ↓
Livewire AJAX → Chat::sendMessage()
   1. validate
   2. Message::create([...])
   3. Conversation::update(['last_message_at' => now()])
   4. broadcast(new MessageSent($message))->toOthers()
   5. append to local $messages array (so User A sees it immediately)
   6. loadConversations() (refresh sidebar ordering)
        ↓
Reverb pushes the event to PresenceChannel('conversation.{id}')
        ↓
User B's browser: Echo.join('conversation.{id}').listen('MessageSent', callback)
        ↓
callback runs:
   - if message.conversation_id !== currently-open conversation → increment unread badge
   - addToast(senderName, preview, convId)
   - Livewire.dispatch('refreshMessages')       (re-renders message list if open)
   - Livewire.dispatch('refreshConversations')  (always — updates sidebar preview/order)
```

### Why `->toOthers()` matters

Excludes the sender's own browser connection from receiving the broadcast — they already see the message via the normal Livewire re-render in step 5 above. Without `toOthers()`, the sender would receive a duplicate event.

### Presence (online/offline dots)

`Echo.join()` (not `Echo.private()`) is used specifically because `PresenceChannel` tracks who is currently connected:

```js
Echo.join(`conversation.${convId}`)
    .here((users) => {
        /* initial roster on join */
    })
    .joining((user) => {
        /* someone else connected */
    })
    .leaving((user) => {
        /* someone else disconnected */
    })
    .listen("MessageSent", callback);
```

`.here()` fires once immediately with the full current roster. `.joining()`/`.leaving()` fire afterward whenever presence changes — this is what flips the green/grey status dot without any polling.

### Role change propagation

```
Admin clicks "Make Admin" → UsersPage::changeRole()
        ↓
$user->syncRoles(['admin'])
broadcast(new RoleChanged($user->id, 'admin'))
        ↓
PrivateChannel('App.Models.User.{id}') → only that user's browser
        ↓
Echo.private(`App.Models.User.${myId}`).listen('RoleChanged', (e) => {
    addToast('Role Updated', `Your role is now ${e.new_role}`);
    setTimeout(() => location.reload(), 2000);
});
```

A full page reload is used here (unlike messages) because role affects route middleware (`role:admin|super_admin` on `/users`) and multiple UI surfaces simultaneously — reloading guarantees everything is consistent rather than patching individual DOM pieces.

### File uploads bypass Livewire

File uploads go through a direct `fetch()` POST to `ChatController::uploadFile()` rather than a Livewire action, to avoid Livewire's temporary upload overhead for what is otherwise a simple multipart form submission:

```js
async function uploadFile(input, convId) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("_token", csrfToken);
    const res = await fetch(`/conversations/${convId}/upload`, {
        method: "POST",
        body: formData,
    });
    // ...
    Livewire.dispatch("fileUploaded"); // tells Chat.php to reload messages
}
```

The controller still validates mime types, enforces the 10MB limit, checks `$user->storageRemaining()` against the quota, increments `storage_used`, creates the `Message` row, and broadcasts `MessageSent` — identical to the original API behavior.

---

## Authentication

Two independent guards are configured in `config/auth.php`:

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

|                                  | `web` guard                                         | `api` guard                                          |
| -------------------------------- | --------------------------------------------------- | ---------------------------------------------------- |
| Used by                          | Livewire components (`Login.php`, `Chat.php`, etc.) | `routes/api.php`, `AuthController.php`               |
| Credential storage               | Encrypted session cookie                            | Bearer token (client-managed)                        |
| Login call                       | `Auth::guard('web')->attempt([...])`                | `Auth::guard('api')->attempt([...])` returns a token |
| Currently used by the browser UI | Yes                                                 | No (dormant, available for external clients)         |

The JWT API routes and code remain fully functional and untouched — they simply aren't called by anything in the current browser session. This separation means a future mobile app or third-party integration could use `/api/*` with JWT without any changes to the existing web app.

See [SETUP.md](SETUP.md) for installation steps and environment configuration.
