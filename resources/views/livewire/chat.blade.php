<div class="chat-layout" x-data="chatApp()" x-init="init()">

    {{-- ── Toast notifications ── --}}
    <div class="toast-container" x-cloak>
        <template x-for="toast in toasts" :key="toast.id">
            <div class="toast" @click="dismissToast(toast.id)">
                <div class="toast-avatar" :style="avatarStyle(toast.sender)" x-text="initials(toast.sender)"></div>
                <div class="toast-body">
                    <div class="toast-sender" x-text="toast.sender"></div>
                    <div class="toast-msg" x-text="toast.message"></div>
                </div>
                <button class="toast-close" @click.stop="dismissToast(toast.id)">✕</button>
            </div>
        </template>
    </div>

    {{-- ══════════════════════════════
         SIDEBAR
    ══════════════════════════════ --}}
    <div class="col-sidebar">

        {{-- Top bar --}}
        <div class="sidebar-topbar">
            <div class="sidebar-brand">
                <span class="brand-hex">⬡</span>
                <span class="brand-name">Relayhub</span>
            </div>
            <div class="sidebar-actions">
                <button class="icon-btn" title="New conversation" wire:click="openNewChat">✎</button>
                <button class="icon-btn danger" title="Logout" wire:click="logout">⏻</button>
            </div>
        </div>

        {{-- Current user --}}
        <button class="sidebar-me sidebar-me-btn" wire:click="openEditProfile" title="Edit profile">
            <x-avatar :name="$user->name" :avatar="$user->avatar" :size="34" />
            <span class="sidebar-me-name">{{ $user->name }}</span>
            <span class="online-dot"></span>
        </button>

        {{-- Search bar (client-side filter, no wire needed) --}}
        <div class="sidebar-search-wrap">
            <input class="sidebar-search" placeholder="Search conversations…"
                   x-model="convSearch" />
        </div>

        {{-- Conversation list --}}
        <div class="conv-scroll">
            @if(count($conversations) === 0)
                <p class="conv-empty">No conversations yet.<br>Start one with ✎</p>
            @endif

            @foreach($conversations as $conv)
                @php $isActive = $activeConvId && $activeConvId === $conv['id']; @endphp
                <button
                    class="conv-item {{ $isActive ? 'active' : '' }}"
                    wire:click="selectConversation({{ $conv['id'] }})"
                    wire:key="conv-{{ $conv['id'] }}"
                    x-show="!convSearch || '{{ strtolower($conv['other']['name'] ?? '') }}'.includes(convSearch.toLowerCase())"
                >
                    <div class="conv-avatar-wrap">
                        <x-avatar
                            :name="$conv['other']['name'] ?? '?'"
                            :avatar="$conv['other']['avatar'] ?? null"
                            :size="44"
                        />
                        <span class="conv-dot"
                              :class="onlineIds.has({{ $conv['other']['id'] ?? 0 }}) ? 'online' : ''">
                        </span>
                    </div>
                    <div class="conv-info">
                        <div class="conv-name">{{ $conv['other']['name'] ?? 'Unknown' }}</div>
                        <div class="conv-preview">
                            @if(!empty($conv['latest_message']['file_name']))
                                📎 {{ $conv['latest_message']['file_name'] }}
                            @elseif(!empty($conv['latest_message']['body']))
                                {{ Str::limit($conv['latest_message']['body'], 40) }}
                            @else
                                No messages yet
                            @endif
                        </div>
                    </div>
                    <div class="conv-right">
                        @if(!empty($conv['last_message_at']))
                            <div class="conv-time">
                                {{ \Carbon\Carbon::parse($conv['last_message_at'])->format('h:i A') }}
                            </div>
                        @endif
                        <div class="unread-badge"
                             x-show="(unread[{{ $conv['id'] }}] ?? 0) > 0"
                             x-text="(unread[{{ $conv['id'] }}] ?? 0) > 99 ? '99+' : unread[{{ $conv['id'] }}]"
                             x-cloak>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Admin link — checks Spatie roles --}}
        @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))
            <div class="sidebar-admin-section">
                <a href="{{ route('users') }}" class="manage-users-btn">
                    <span class="manage-users-icon">👥</span>
                    Manage Users
                </a>
            </div>
        @endif

        {{-- Storage bar — uses User model storage_used_formatted etc. --}}
        @if($storageInfo)
            <div class="storage-bar-wrap">
                <div class="storage-bar-label">
                    <span>Storage</span>
                    <span>{{ $storageInfo['used_fmt'] }} / {{ $storageInfo['quota_fmt'] }}</span>
                </div>
                <div class="storage-bar-track">
                    <div class="storage-bar-fill {{ $storageInfo['percentage'] > 90 ? 'danger' : ($storageInfo['percentage'] > 70 ? 'warning' : '') }}"
                         style="width:{{ $storageInfo['percentage'] }}%">
                    </div>
                </div>
                <div class="storage-bar-pct">{{ $storageInfo['percentage'] }}% used</div>
            </div>
        @endif

    </div>{{-- /col-sidebar --}}

    {{-- Edit Profile Panel (overlay) --}}
    @if($showEditProfile)
        <livewire:edit-profile-panel :key="'epp-' . $user->id" />
    @endif

    {{-- ══════════════════════════════
         MESSAGES COLUMN
    ══════════════════════════════ --}}
    <div class="col-messages">

        @if(!$activeConvId)
            {{-- Empty state --}}
            <div class="no-conv">
                <div class="no-conv-icon">⬡</div>
                <p>Pick a conversation or start a new one</p>
                <button class="btn-primary" wire:click="openNewChat">New Conversation</button>
            </div>

        @else

            {{-- Message header --}}
            <div class="msg-header">
                <button class="msg-header-profile-btn" wire:click="toggleProfile">
                    <x-avatar
                        :name="$other?->name ?? '?'"
                        :avatar="$other?->avatar ?? null"
                        :size="38"
                    />
                    <div class="msg-header-info">
                        <div class="msg-header-name">{{ $other?->name }}</div>
                        <div class="msg-header-status">
                            <span :class="onlineIds.has({{ $other?->id ?? 0 }}) ? 'online-dot' : 'offline-dot'"></span>
                            <span x-text="onlineIds.has({{ $other?->id ?? 0 }}) ? 'Online' : 'Offline'"></span>
                        </div>
                    </div>
                </button>
                <div class="msg-header-actions">
                    <button class="icon-btn-light" wire:click="$toggle('showSearch')" title="Search messages">🔍</button>
                </div>
            </div>

            {{-- Search panel --}}
            @if($showSearch)
                <div class="search-panel">
                    <div class="search-input-wrap">
                        <input
                            class="search-input"
                            placeholder="Search messages…"
                            wire:model.live.debounce.400ms="searchQuery"
                            wire:change="searchMessages"
                            autofocus
                        />
                        <span wire:loading wire:target="searchMessages" class="search-spinner">⏳</span>
                    </div>
                    @if($searchQuery)
                        <div class="search-results">
                            @forelse($searchResults as $result)
                                <button class="search-result-item"
                                        onclick="scrollToMessage({{ $result['id'] }})">
                                    <div class="search-result-sender">{{ $result['sender']['name'] ?? '' }}</div>
                                    <div class="search-result-body">
                                        {!! highlightSearch($result['body'], $searchQuery) !!}
                                    </div>
                                    <div class="search-result-time">
                                        {{ \Carbon\Carbon::parse($result['created_at'])->format('d M, h:i A') }}
                                    </div>
                                </button>
                            @empty
                                <p class="search-empty">No messages found for "{{ $searchQuery }}"</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif

            {{-- Messages area --}}
            <div class="messages-scroll" id="messages-scroll">
                <div wire:loading wire:target="selectConversation" class="msgs-loading">Loading…</div>

                @foreach($messages as $msg)
                    @php $isOwn = $msg['sender_id'] === auth()->id(); @endphp
                    <div
                        id="msg-{{ $msg['id'] }}"
                        class="msg-row {{ $isOwn ? 'own' : 'other' }}"
                        wire:key="msg-{{ $msg['id'] }}"
                    >
                        @if(!$isOwn)
                            <x-avatar
                                :name="$msg['sender']['name'] ?? ''"
                                :avatar="$msg['sender']['avatar'] ?? null"
                                :size="30"
                            />
                        @endif

                        <div class="msg-wrap">
                            {{-- Reply quote --}}
                            @if(!empty($msg['reply_to']))
                                <div class="msg-reply-quote">
                                    <span class="msg-reply-name">{{ $msg['reply_to']['sender']['name'] ?? '' }}</span>
                                    <span class="msg-reply-body">{{ $msg['reply_to']['body'] }}</span>
                                </div>
                            @endif

                            <div class="bubble">
                                {{-- Image file (uses Message::isImage() logic) --}}
                                @if(!empty($msg['file_path']) && str_starts_with($msg['file_type'] ?? '', 'image/'))
                                    <a href="{{ asset('storage/' . $msg['file_path']) }}" target="_blank">
                                        <img
                                            src="{{ asset('storage/' . $msg['file_path']) }}"
                                            alt="{{ $msg['file_name'] }}"
                                            class="msg-image"
                                        />
                                    </a>
                                @endif

                                {{-- Non-image file --}}
                                @if(!empty($msg['file_path']) && !str_starts_with($msg['file_type'] ?? '', 'image/'))
                                    <a href="{{ asset('storage/' . $msg['file_path']) }}"
                                       target="_blank"
                                       download="{{ $msg['file_name'] }}"
                                       class="msg-file">
                                        <span class="msg-file-icon">📄</span>
                                        <div class="msg-file-info">
                                            <div class="msg-file-name">{{ $msg['file_name'] }}</div>
                                            {{-- Uses Message::getFileSizeFormattedAttribute() --}}
                                            <div class="msg-file-size">{{ $msg['file_size_fmt'] }}</div>
                                        </div>
                                        <span class="msg-file-download">↓</span>
                                    </a>
                                @endif

                                {{-- Text body --}}
                                @if($msg['body'])
                                    <span>{{ $msg['body'] }}</span>
                                @endif

                                <button class="msg-reply-btn" title="Reply"
                                        wire:click="setReplyTo({{ $msg['id'] }})">↩</button>
                            </div>

                            <div class="msg-time">
                                {{ \Carbon\Carbon::parse($msg['created_at'])->format('h:i A') }}
                            </div>
                        </div>

                        @if($isOwn)
                            <x-avatar
                                :name="$msg['sender']['name'] ?? ''"
                                :avatar="$msg['sender']['avatar'] ?? null"
                                :size="30"
                            />
                        @endif
                    </div>
                @endforeach

                <div id="msg-bottom"></div>
            </div>

            {{-- Reply banner --}}
            @if($replyToMsg)
                <div class="reply-banner">
                    <div>
                        <div class="reply-banner-label">↩ Replying to {{ $replyToMsg['sender']['name'] ?? '' }}</div>
                        <div class="reply-banner-preview">
                            {{ !empty($replyToMsg['file_name']) ? '📎 ' . $replyToMsg['file_name'] : ($replyToMsg['body'] ?? '') }}
                        </div>
                    </div>
                    <button class="reply-cancel" wire:click="cancelReply">✕</button>
                </div>
            @endif

            {{-- Input row --}}
            <div class="input-row">
                {{-- File input — uses ChatController::uploadFile() via fetch --}}
                <input type="file" id="file-input" style="display:none"
                       accept="image/jpeg,image/png,image/gif,image/webp,.pdf,.doc,.docx,.txt,.zip"
                       onchange="uploadFile(this, {{ $activeConvId }})">

                <button class="attach-btn" id="attach-btn"
                        onclick="document.getElementById('file-input').click()"
                        title="Attach file">📎</button>

                <textarea
                    class="msg-input"
                    placeholder="Type your message here…"
                    rows="1"
                    wire:model.defer="text"
                    x-on:keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage() }"
                ></textarea>

                <button class="send-btn"
                        wire:click="sendMessage"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage">
                    <span wire:loading.remove wire:target="sendMessage">↑</span>
                    <span wire:loading wire:target="sendMessage">…</span>
                </button>
            </div>

        @endif
    </div>{{-- /col-messages --}}

    {{-- ══════════════════════════════
         PROFILE PANEL
    ══════════════════════════════ --}}
    @if($showProfile && $activeConv && $other)
        <div class="col-profile">
            <div class="profile-content">
                <button class="profile-close-btn" wire:click="toggleProfile">✕</button>

                <div class="profile-avatar-wrap">
                    <x-avatar :name="$other->name" :avatar="$other->avatar" :size="80" />
                </div>
                <div class="profile-name">{{ $other->name }}</div>
                <div class="profile-email">{{ $other->email }}</div>
                @if($other->bio)
                    <div class="profile-bio">{{ $other->bio }}</div>
                @endif
                <div class="profile-badge">Active</div>
                <div class="profile-divider"></div>

                <livewire:profile-tabs
                    :conversation="$activeConv"
                    :messageCount="count($messages)"
                    :key="'pt-' . $activeConv->id"
                />
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════
         NEW CONVERSATION MODAL
    ══════════════════════════════ --}}
    @if($showNewChat)
        <div class="modal-overlay" wire:click="$set('showNewChat', false)">
            <div class="modal" wire:click.stop>
                <div class="modal-header">
                    <h3>New Conversation</h3>
                    <button class="modal-close" wire:click="$set('showNewChat', false)">✕</button>
                </div>
                <input
                    class="modal-search"
                    placeholder="Search users…"
                    wire:model.live.debounce.200ms="userSearch"
                    autofocus
                />
                <div class="modal-list">
                    @forelse($filteredUsers as $u)
                        <button
                            class="modal-user"
                            wire:click="startConversation({{ $u['id'] }})"
                            wire:key="mu-{{ $u['id'] }}"
                        >
                            <x-avatar :name="$u['name']" :avatar="$u['avatar'] ?? null" :size="38" />
                            <div>
                                <div class="modal-user-name">{{ $u['name'] }}</div>
                                <div class="modal-user-email">{{ $u['email'] }}</div>
                            </div>
                        </button>
                    @empty
                        <p class="modal-empty">No users found</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

</div>{{-- /chat-layout --}}

@push('scripts')
<script>
function chatApp() {
    return {
        toasts:    [],
        unread:    {},
        onlineIds: new Set(),
        channels:  new Map(),
        convSearch: '',

        init() {
            // Scroll to bottom when Livewire updates messages
            window.addEventListener('scrollToBottom', () => {
                setTimeout(() => {
                    document.getElementById('msg-bottom')
                        ?.scrollIntoView({ behavior: 'smooth' });
                }, 60);
            });

            // Clear unread count when user selects a conversation
            window.addEventListener('convSelected', (e) => {
                delete this.unread[e.detail.convId];
            });

            // Setup Echo presence channels for each conversation
            this.setupEcho();
        },

        setupEcho() {
            @foreach($conversations as $conv)
            this.joinConv({{ $conv['id'] }}, {{ $conv['other']['id'] ?? 0 }});
            @endforeach
        },

        joinConv(convId, otherId) {
            if (this.channels.has(convId)) return;

            // PresenceChannel — matches your MessageSent event which uses PresenceChannel
            // channels.php returns {id, name} for presence user list
            const channel = window.Echo.join(`conversation.${convId}`)
                .here((users) => {
                    users.forEach(u => this.onlineIds.add(Number(u.id)));
                })
                .joining((u) => {
                    this.onlineIds.add(Number(u.id));
                })
                .leaving((u) => {
                    this.onlineIds.delete(Number(u.id));
                })
                .listen('MessageSent', (e) => {
                    console.log('BROADCAST RECEIVED', e);
                    console.log('MESSAGE DATA', e.message);

                    const msg = e.message;

                    console.log('conversation_id:', msg.conversation_id);
                    console.log('activeConvId:', $wire.activeConvId);

                    if (msg.conversation_id == $wire.activeConvId) {
                        console.log('RELOADING MESSAGES');
                        $wire.loadMessages();
                    }

                    $wire.loadConversations();
                });

            this.channels.set(convId, channel);
        },

        addToast(sender, message) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, sender, message });
            setTimeout(() => this.dismissToast(id), 5000);
        },

        dismissToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },

        initials(name) {
            return (name || '?')
                .split(' ')
                .map(w => w[0] || '')
                .join('')
                .toUpperCase()
                .slice(0, 2);
        },

        avatarStyle(name) {
            const hue = [...(name || '')].reduce((a, c) => a + c.charCodeAt(0), 0) % 360;
            return `background:hsl(${hue},50%,55%);`
                 + `width:36px;height:36px;border-radius:50%;`
                 + `display:flex;align-items:center;justify-content:center;`
                 + `font-size:13px;font-weight:700;color:#fff;flex-shrink:0;`;
        },
    };
}

// ── File upload — calls ChatController::uploadFile() via fetch
// Keeps quota check, mime validation, storage increment — all in controller
async function uploadFile(input, convId) {
    const file = input.files[0];
    if (!file) return;

    // Client-side size check (mirrors controller max:10240 = 10MB)
    if (file.size > 10 * 1024 * 1024) {
        alert('File too large. Maximum size is 10MB.');
        input.value = '';
        return;
    }

    const btn = document.getElementById('attach-btn');
    btn.textContent = '⏳';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

    try {
        const res = await fetch(`/conversations/${convId}/upload`, {
            method: 'POST',
            body: formData,
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            if (err.error === 'Storage quota exceeded') {
                alert(`Storage full!\nUsed: ${err.used} of ${err.quota}`);
            } else {
                alert('Upload failed. Please try again.');
            }
            return;
        }

        // Reload messages to show uploaded file
        Livewire.dispatch('fileUploaded');

    } catch {
        alert('Upload failed. Please try again.');
    } finally {
        btn.textContent = '📎';
        btn.disabled = false;
        input.value = '';
    }
}

// ── Scroll to message from search results
function scrollToMessage(msgId) {
    const el = document.getElementById(`msg-${msgId}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('highlight');
        setTimeout(() => el.classList.remove('highlight'), 2000);
    }
}
</script>
@endpush
