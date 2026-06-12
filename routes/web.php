<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes — Relayhub (Livewire)
|
| This REPLACES the Inertia-based web.php.
| The existing routes/api.php is kept 100% untouched — JWT API still works.
|--------------------------------------------------------------------------
*/

// ── Guest only (redirect to / if already logged in via web guard)
Route::middleware('guest')->group(function () {
    Route::get('/login',    \App\Livewire\Login::class)->name('login');
    Route::get('/register', \App\Livewire\Register::class)->name('register');
});

// ── Authenticated (web guard / session)
Route::middleware('auth')->group(function () {

    // Main chat page
    Route::get('/', \App\Livewire\Chat::class)->name('chat');

    // File upload — POST from JS fetch() in chat.blade.php
    // Reuses ChatController::uploadFile() — no changes to controller needed
    Route::post('/conversations/{id}/upload',
        [\App\Http\Controllers\ChatController::class, 'uploadFile']
    )->name('upload.file');

    // Admin only
    Route::middleware('role:admin|super_admin')->group(function () {
        Route::get('/users', \App\Livewire\UsersPage::class)->name('users');
    });

    // Logout
    Route::post('/logout', function () {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('login');
    })->name('logout');

});

// ── Catch-all → redirect to /
Route::fallback(fn() => redirect('/'));
