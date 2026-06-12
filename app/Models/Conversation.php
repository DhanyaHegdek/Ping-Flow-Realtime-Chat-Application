<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // A conversation belongs to user one
    public function userOne() {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    // A conversation belongs to user two
    public function userTwo() {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    // A conversation has many messages
    public function messages() {
        return $this->hasMany(Message::class);
    }

    // Get the latest message
    public function latestMessage() {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}