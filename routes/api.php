<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Broadcast;

// Public routes 
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Protected routes 
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Own profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // Users list (for New Chat modal)
    Route::get('/users', function () {
        return response()->json(
            \App\Models\User::select(['id', 'name', 'email'])
                ->orderBy('name', 'asc')
                ->get()
        );
    });

    // Chat — requires chat permission 
   
    Route::post('/conversations',               [ChatController::class, 'startConversation']);
    Route::get('/conversations',                [ChatController::class, 'getConversations']);
    Route::get('/conversations/{id}/messages/search', [ChatController::class, 'searchMessages']);
    Route::get('/conversations/{id}/messages',  [ChatController::class, 'getMessages']);
    Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
    

    // Admin — requires admin role 
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users',           [AdminController::class, 'listUsers']);
        Route::get('/users/{id}',      [AdminController::class, 'viewProfile']);
        Route::post('/users',          [AdminController::class, 'createUser']);
        Route::delete('/users/{id}',   [AdminController::class, 'deleteUser']);
        Route::put('/users/{id}/role', [AdminController::class, 'changeRole']);
    });

    // Broadcasting auth
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });

    Route::post('/conversations/{id}/upload', [ChatController::class, 'uploadFile']);

    Route::get('/conversations/{id}/files', [ChatController::class, 'getFiles']);

    Route::get('/storage-info', [ChatController::class, 'storageInfo']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);

});