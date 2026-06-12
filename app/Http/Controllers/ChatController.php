<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Events\MessageSent;


class ChatController extends Controller
{
    // Start or get existing conversation with someone
    public function startConversation(Request $request) {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $authId = Auth::id();
        $otherId = $request->user_id;

        // Prevent chatting with yourself
        if ($authId === $otherId) {
            return response()->json(['error' => 'Cannot start conversation with yourself'], 400);
        }

        // Check if conversation already exists
        $conversation = Conversation::where(function(Builder $q) use ($authId, $otherId) {
            $q->where('user_one_id', $authId)->where('user_two_id', $otherId);
        })->orWhere(function(Builder $q) use ($authId, $otherId) {
            $q->where('user_one_id', $otherId)->where('user_two_id', $authId);
        })->first();

        // Create if it doesn't exist
        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $authId,
                'user_two_id' => $otherId,
            ]);
        }

        return response()->json($conversation->load(['userOne', 'userTwo']));
    }

    // Get all conversations for logged in user
    public function getConversations() {
        $userId = Auth::id();

        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne', 'userTwo', 'latestMessage'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return response()->json($conversations);
    }

    // Get all messages in a conversation
    public function getMessages($conversationId) {
        $userId = Auth::id();

        // Make sure user belongs to this conversation
        $conversation = Conversation::where('id', $conversationId)
            ->where(function(Builder $q) use ($userId) {
                $q->where('user_one_id', $userId)
                  ->orWhere('user_two_id', $userId);
            })->firstOrFail();

        $messages = Message::where('conversation_id', $conversationId)
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    // Send a message
    public function sendMessage(Request $request, $conversationId) {
        $request->validate([
            'body'        => 'required|string|max:5000',
            'reply_to_id' => 'nullable|exists:messages,id',
        ]);

        $userId = Auth::id();

        // Make sure user belongs to this conversation
        $conversation = Conversation::where('id', $conversationId)
            ->where(function($q) use ($userId) {
                $q->where('user_one_id', $userId)
                  ->orWhere('user_two_id', $userId);
            })->firstOrFail();

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $userId,
            'body'            => $request->body,
            'reply_to_id'     => $request->reply_to_id,
        ]);

        // Update last message time on conversation
        $conversation->update(['last_message_at' => now()]);

        //  Broadcast to the other user in real-time
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message->load(['sender', 'replyTo.sender']), 201);
    }

    public function searchMessages(Request $request, $conversationId) {
        $request->validate(['q' => 'required|string|min:1|max:255']);

        $userId = Auth::id();

        // Security — user must belong to this conversation
        Conversation::where('id', $conversationId)
            ->where(function($q) use ($userId) {
                $q->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId);
            })->firstOrFail();

        $messages = Message::where('conversation_id', $conversationId)
            ->where('body', 'ILIKE', '%' . $request->q . '%') // ILIKE = case-insensitive for PostgreSQL
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($messages);
    }

    public function uploadFile(Request $request, $conversationId)
    {
        $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt,zip',
            ],
        ]);

        $userId = Auth::id();
        $user   = Auth::user();

        $conversation = Conversation::where('id', $conversationId)
            ->where(function($q) use ($userId) {
                $q->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId);
            })->firstOrFail();

        $file     = $request->file('file');
        $fileSize = $file->getSize();
        $fileName = $file->getClientOriginalName();
        $fileType = $file->getMimeType();

        // Check quota
        if ($user->storageRemaining() < $fileSize) {
            return response()->json([
                'error' => 'Storage quota exceeded',
                'used'  => $user->storage_used_formatted,
                'quota' => $user->storage_quota_formatted,
            ], 422);
        }

        // Store file directly (no compression for now)
        $filePath = $file->store('uploads', 'public');

        // Update storage usage
        $user->increment('storage_used', $fileSize);

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $userId,
            'body'            => '',
            'file_path'       => $filePath,
            'file_name'       => $fileName,
            'file_type'       => $fileType,
            'file_size'       => $fileSize,
        ]);

        $conversation->update(['last_message_at' => now()]);
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message->load(['sender', 'replyTo.sender']), 201);
    }
    // ── Helper
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    public function getFiles($conversationId)
    {
        $userId = Auth::id();

        Conversation::where('id', $conversationId)
            ->where(function($q) use ($userId) {
                $q->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId);
            })->firstOrFail();

        $files = Message::where('conversation_id', $conversationId)
            ->whereNotNull('file_path')
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($files);
    }

    public function storageInfo()
    {
        $user = Auth::user();
        return response()->json([
            'used'       => $user->storage_used,
            'quota'      => $user->storage_quota,
            'remaining'  => $user->storageRemaining(),
            'percentage' => $user->storagePercentage(),
            'used_fmt'   => $user->storage_used_formatted,
            'quota_fmt'  => $user->storage_quota_formatted,
        ]);
    }

}