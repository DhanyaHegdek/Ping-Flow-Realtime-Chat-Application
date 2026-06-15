<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read User|null    $userOne
 * @property-read User|null    $userTwo
 * @property-read Message|null $latestMessage
 */
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
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    // A conversation belongs to user two
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    // A conversation has many messages
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // Get the latest message
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}