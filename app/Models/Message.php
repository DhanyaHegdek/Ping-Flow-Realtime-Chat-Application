<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null  $created_at
 * @property-read Carbon|null  $read_at
 * @property-read User|null    $sender
 * @property-read Message|null $replyTo
 */
class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'reply_to_id',
        'read_at',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    // Message belongs to a conversation
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // Message belongs to a sender (user)
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Message can reply to another message
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    // Message can have many replies
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->file_type ?? '', 'image/');
    }

    // Helper — formatted file size
    public function getFileSizeFormattedAttribute(): string
    {
        if (!$this->file_size) return '';
        if ($this->file_size < 1024) return $this->file_size . ' B';
        if ($this->file_size < 1048576) return round($this->file_size / 1024, 1) . ' KB';
        return round($this->file_size / 1048576, 1) . ' MB';
    }
}