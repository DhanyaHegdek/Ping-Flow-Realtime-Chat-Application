<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

 
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    /** @noinspection PhpUndefinedMethodInspection */
    $exists = Conversation::where('id', $conversationId)
        ->where(function ($q) use ($user) {
            $q->where('user_one_id', $user->id)
              ->orWhere('user_two_id', $user->id);
        })
        ->exists();

    if (!$exists) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

