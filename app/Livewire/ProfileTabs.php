<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;

class ProfileTabs extends Component
{
    public Conversation $conversation;
    public int          $messageCount = 0;
    public string       $tab          = 'info';
    public array        $files        = [];
    public bool         $loadingFiles = false;

    public function mount(Conversation $conversation, int $messageCount = 0): void
    {
        $this->conversation = $conversation;
        $this->messageCount = $messageCount;
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        if ($tab === 'media') {
            $this->loadFiles();
        }
    }

    // Mirrors ChatController::getFiles()
    public function loadFiles(): void
    {
        $this->loadingFiles = true;

        $this->files = Message::where('conversation_id', $this->conversation->id)
            ->whereNotNull('file_path')
            ->with('sender')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($m) => [
                'id'            => $m->id,
                'file_path'     => $m->file_path,
                'file_name'     => $m->file_name,
                'file_type'     => $m->file_type,
                'file_size_fmt' => $m->file_size_formatted, // uses Message model accessor
                'created_at'    => $m->created_at->toISOString(),
                'sender'        => $m->sender ? ['name' => $m->sender->name] : null,
            ])
            ->toArray();

        $this->loadingFiles = false;
    }

    public function render()
    {
        $images = array_values(array_filter(
            $this->files, fn($f) => str_starts_with($f['file_type'] ?? '', 'image/')
        ));
        $docs = array_values(array_filter(
            $this->files, fn($f) => !str_starts_with($f['file_type'] ?? '', 'image/')
        ));

        return view('livewire.profile-tabs', compact('images', 'docs'));
    }
}
