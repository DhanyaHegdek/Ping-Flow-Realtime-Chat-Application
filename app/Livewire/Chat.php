<?php

namespace App\Livewire;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Chat extends Component
{
    // ── Active conversation
    public ?int  $activeConvId  = null;

    // ── Lists
    public array $conversations = [];
    public array $messages      = [];
    public array $allUsers      = [];

    // ── Message input
    public string $text         = '';
    public ?int   $replyToId    = null;
    public ?array $replyToMsg   = null;

    // ── UI toggles
    public bool $showNewChat     = false;
    public bool $showProfile     = false;
    public bool $showEditProfile = false;

    // ── User search in new chat modal
    public string $userSearch   = '';

    // ── Message search
    public bool   $showSearch    = false;
    public string $searchQuery   = '';
    public array  $searchResults = [];

    // ── Storage (from User model methods)
    public ?array $storageInfo  = null;

    // ── Livewire listeners
    protected $listeners = [
        'fileUploaded'           => 'loadMessages',
        'closeEditProfilePanel'  => 'closeEditProfile',
        'profileUpdated'         => '$refresh',
    ];

    public function closeEditProfile(): void
    {
        $this->showEditProfile = false;
    }

    public function openEditProfile(): void
    {
        $this->showEditProfile = true;
    }

    // ── Mount
    public function mount(): void
    {
        $this->loadConversations();
        $this->loadStorageInfo();
    }

    // ── Load conversations
    // Mirrors ChatController::getConversations()
    public function loadConversations(): void
    {
        $userId = Auth::id();

        $this->conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne', 'userTwo', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn($c) => $this->serializeConv($c))
            ->toArray();
    }

    private function serializeConv(Conversation $c): array
    {
        $userId = Auth::id();
        $other  = $c->user_one_id === $userId ? $c->userTwo : $c->userOne;

        return [
            'id'    => $c->id,
            'other' => $other ? [
                'id'     => $other->id,
                'name'   => $other->name,
                'email'  => $other->email,
                'avatar' => $other->avatar,
            ] : null,
            'latest_message' => $c->latestMessage ? [
                'body'      => $c->latestMessage->body,
                'file_name' => $c->latestMessage->file_name,
                'sender_id' => $c->latestMessage->sender_id,
            ] : null,
            'last_message_at' => $c->last_message_at?->toISOString(),
        ];
    }

    // ── Load storage info
    // Uses same User model methods as ChatController::storageInfo()
    public function loadStorageInfo(): void
    {
        try {
            $user = Auth::user();
            $this->storageInfo = [
                'used_fmt'   => $user->storage_used_formatted,
                'quota_fmt'  => $user->storage_quota_formatted,
                'percentage' => min((int) $user->storagePercentage(), 100),
            ];
        } catch (\Exception) {
            $this->storageInfo = null;
        }
    }

    // ── Select conversation
    public function selectConversation(int $convId): void
    {
        $userId = Auth::id();

        $conv = Conversation::where('id', $convId)
            ->where(fn($q) => $q
                ->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId)
            )->firstOrFail();

        $this->activeConvId  = $conv->id;
        $this->showProfile   = false;
        $this->showSearch    = false;
        $this->searchQuery   = '';
        $this->searchResults = [];

        $this->loadMessages();
        $this->dispatch('convSelected', convId: $convId);
    }

    // ── Load messages
    // Mirrors ChatController::getMessages()
    public function loadMessages(): void
    {
        if (!$this->activeConvId) return;

        $this->messages = Message::where('conversation_id', $this->activeConvId)
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => $this->serializeMessage($m))
            ->toArray();

        $this->dispatch('scrollToBottom');
    }

    private function serializeMessage(Message $m): array
    {
        return [
            'id'            => $m->id,
            'body'          => $m->body,
            'sender_id'     => $m->sender_id,
            'file_path'     => $m->file_path,
            'file_name'     => $m->file_name,
            'file_type'     => $m->file_type,
            'file_size'     => $m->file_size,
            'file_size_fmt' => $m->file_size_formatted, // from Message accessor
            'created_at'    => $m->created_at->toISOString(),
            'read_at'       => $m->read_at?->toISOString(),
            'sender'        => $m->sender ? [
                'id'     => $m->sender->id,
                'name'   => $m->sender->name,
                'avatar' => $m->sender->avatar,
            ] : null,
            'reply_to' => $m->replyTo ? [
                'id'   => $m->replyTo->id,
                'body' => $m->replyTo->body,
                'sender' => $m->replyTo->sender ? [
                    'name' => $m->replyTo->sender->name,
                ] : null,
            ] : null,
        ];
    }

    // ── Send message
    // Mirrors ChatController::sendMessage()
    public function sendMessage(): void
    {
        if (!$this->activeConvId || !trim($this->text)) return;

        $this->validate(['text' => 'required|string|max:5000']);

        $userId = Auth::id();

        $conversation = Conversation::where('id', $this->activeConvId)
            ->where(fn($q) => $q
                ->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId)
            )->firstOrFail();

        $message = Message::create([
            'conversation_id' => $this->activeConvId,
            'sender_id'       => $userId,
            'body'            => trim($this->text),
            'reply_to_id'     => $this->replyToId,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Broadcast to other user — same as ChatController
        broadcast(new MessageSent($message))->toOthers();

        // Add to local messages array without full reload
        $this->messages[] = $this->serializeMessage(
            $message->fresh(['sender', 'replyTo.sender'])
        );

        $this->text      = '';
        $this->replyToId = null;
        $this->replyToMsg = null;

        $this->loadConversations();
        $this->dispatch('scrollToBottom');
    }

    // ── Start conversation
    // Mirrors ChatController::startConversation()
    public function startConversation(int $userId): void
    {
        $authId = Auth::id();

        if ($authId === $userId) return;

        $conversation = Conversation::where(fn($q) => $q
            ->where('user_one_id', $authId)
            ->where('user_two_id', $userId)
        )->orWhere(fn($q) => $q
            ->where('user_one_id', $userId)
            ->where('user_two_id', $authId)
        )->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $authId,
                'user_two_id' => $userId,
            ]);
        }

        $this->showNewChat = false;
        $this->userSearch  = '';
        $this->loadConversations();
        $this->selectConversation($conversation->id);
    }

    // ── Open new chat modal — load all users
    public function openNewChat(): void
    {
        $this->allUsers = User::where('id', '!=', Auth::id())
            ->select('id', 'name', 'email', 'avatar')
            ->orderBy('name')
            ->get()
            ->toArray();

        $this->showNewChat = true;
        $this->userSearch  = '';
    }

    // ── Reply
    public function setReplyTo(int $msgId): void
    {
        $msg = collect($this->messages)->firstWhere('id', $msgId);
        if ($msg) {
            $this->replyToId  = $msgId;
            $this->replyToMsg = $msg;
        }
    }

    public function cancelReply(): void
    {
        $this->replyToId  = null;
        $this->replyToMsg = null;
    }

    // ── Search messages
    // Mirrors ChatController::searchMessages() — uses ILIKE for PostgreSQL
    public function searchMessages(): void
    {
        if (!$this->activeConvId || !trim($this->searchQuery)) {
            $this->searchResults = [];
            return;
        }

        $userId = Auth::id();

        // Security check — user must belong to conversation
        Conversation::where('id', $this->activeConvId)
            ->where(fn($q) => $q
                ->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId)
            )->firstOrFail();

        $this->searchResults = Message::where('conversation_id', $this->activeConvId)
            ->where('body', 'ilike', '%' . $this->searchQuery . '%') // PostgreSQL ILIKE
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'created_at' => $m->created_at->toISOString(),
                'sender'     => ['name' => $m->sender?->name],
            ])
            ->toArray();
    }

    // ── Logout
    public function logout(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect(route('login'), navigate: false);
    }

    // ── Toggle profile panel
    public function toggleProfile(): void
    {
        $this->showProfile = !$this->showProfile;
    }

    // ── Render
    public function render()
    {
        $user = Auth::user();

        // Filter users for modal
        $filteredUsers = collect($this->allUsers)->filter(function ($u) {
            if (!$this->userSearch) return true;
            $q = strtolower($this->userSearch);
            return str_contains(strtolower($u['name']), $q)
                || str_contains(strtolower($u['email']), $q);
        })->values()->toArray();

        // Active conversation + other user
        $activeConv = $this->activeConvId
            ? Conversation::with(['userOne', 'userTwo'])->find($this->activeConvId)
            : null;

        $other = null;
        if ($activeConv) {
            $other = $activeConv->user_one_id === Auth::id()
                ? $activeConv->userTwo
                : $activeConv->userOne;
        }

        return view('livewire.chat', compact('user', 'filteredUsers', 'activeConv', 'other'))
            ->layout('layouts.app');
    }
}
