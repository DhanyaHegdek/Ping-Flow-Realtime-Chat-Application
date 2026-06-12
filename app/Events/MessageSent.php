<?php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
// use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PresenceChannel;
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load(['sender', 'replyTo.sender']);
    }

    public function broadcastOn(): array
    {
        // PrivateChannelAuthenticated users only
        // return [new PrivateChannel('conversation.' . $this->message->conversation_id)];

        // PresenceChannelAuth + see who's online
        return [new PresenceChannel('conversation.' . $this->message->conversation_id)];

    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }


} 