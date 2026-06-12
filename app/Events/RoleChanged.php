<?php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $newRole;

    public function __construct(int $userId, string $newRole)
    {
        $this->userId  = $userId;
        $this->newRole = $newRole;
    }

    public function broadcastOn(): array
    {
        // Broadcast to the specific user's private channel
        return [new PrivateChannel('App.Models.User.' . $this->userId)];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'  => $this->userId,
            'new_role' => $this->newRole,
        ];
    }
}