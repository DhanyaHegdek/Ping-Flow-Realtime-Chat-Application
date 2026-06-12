<div class="up-page">

    {{-- Top bar --}}
    <div class="up-topbar">
        <div class="up-topbar-left">
            <a href="{{ route('chat') }}" class="up-back-btn">← Back to Chat</a>
            <div>
                <h1 class="up-title">Manage Users</h1>
                <p class="up-subtitle">{{ $totalCount }} total users</p>
            </div>
        </div>
        <button class="btn-primary" wire:click="$set('showCreate', true)">+ Create User</button>
    </div>

    {{-- Search --}}
    <div class="up-search-row">
        <input
            class="up-search"
            placeholder="Search by name or email…"
            wire:model.live.debounce.200ms="search"
        />
    </div>

    {{-- Table --}}
    <div class="up-card">
        @if(count($filteredUsers) === 0)
            <div class="up-empty">No users found.</div>
        @else
            <table class="up-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($filteredUsers as $u)
                        <tr wire:key="user-{{ $u['id'] }}">
                            <td>
                                <div class="up-user-cell">
                                    <x-avatar :name="$u['name']" :avatar="$u['avatar'] ?? null" :size="34" />
                                    <span class="up-user-name">
                                        {{ $u['name'] }}
                                        @if($u['id'] === auth()->id())
                                            <span class="up-you">You</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="up-email">{{ $u['email'] }}</td>
                            <td>
                                <span class="up-role up-role-{{ $u['role'] }}">{{ $u['role'] }}</span>
                            </td>
                            <td class="up-date">
                                {{ \Carbon\Carbon::parse($u['created_at'])->format('d M Y') }}
                            </td>
                            <td>
                                <div class="up-actions">
                                    {{-- View profile --}}
                                    <button class="up-btn"
                                            wire:click="$set('viewProfileId', {{ $u['id'] }})">
                                        👤 Profile
                                    </button>

                                    {{-- Change role (mirrors AdminController::changeRole) --}}
                                    @if($u['id'] !== auth()->id())
                                        <button
                                            class="up-btn {{ $u['role'] === 'admin' ? 'up-btn-demote' : 'up-btn-promote' }}"
                                            wire:click="changeRole({{ $u['id'] }}, '{{ $u['role'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="changeRole({{ $u['id'] }}, '{{ $u['role'] }}')"
                                        >
                                            {{ $u['role'] === 'admin' ? 'Make User' : 'Make Admin' }}
                                        </button>

                                        {{-- Delete (mirrors AdminController::deleteUser) --}}
                                        <button
                                            class="up-btn up-btn-delete"
                                            wire:click="deleteUser({{ $u['id'] }})"
                                            wire:confirm="Are you sure you want to delete {{ $u['name'] }}?"
                                        >
                                            🗑 Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Create User Modal --}}
    @if($showCreate)
        <div class="modal-overlay" wire:click="$set('showCreate', false)">
            <div class="modal" wire:click.stop>
                <div class="modal-header">
                    <h3>Create New User</h3>
                    <button class="modal-close" wire:click="$set('showCreate', false)">✕</button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="createUser" class="create-user-form">
                        <div class="field">
                            <label>Name</label>
                            <input type="text" wire:model.defer="newName" placeholder="Full name" required />
                            @error('newName') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input type="email" wire:model.defer="newEmail" placeholder="email@example.com" required />
                            @error('newEmail') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <input type="password" wire:model.defer="newPassword" placeholder="Min. 6 characters" minlength="6" required />
                            @error('newPassword') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label>Role</label>
                            <select wire:model.defer="newRole" class="role-select">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-secondary"
                                    wire:click="$set('showCreate', false)">Cancel</button>
                            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Create User</span>
                                <span wire:loading>Creating…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Profile View Modal --}}
    @if($viewProfileId && $profileUser)
        <div class="modal-overlay" wire:click="$set('viewProfileId', null)">
            <div class="modal modal-sm" wire:click.stop>
                <div class="modal-header">
                    <h3>User Profile</h3>
                    <button class="modal-close" wire:click="$set('viewProfileId', null)">✕</button>
                </div>
                <div class="modal-body">
                    <div class="profile-view">
                        <x-avatar :name="$profileUser->name" :avatar="$profileUser->avatar" :size="64" />
                        <div class="profile-view-name">{{ $profileUser->name }}</div>
                        <div class="profile-view-email">{{ $profileUser->email }}</div>
                        @if($profileUser->bio)
                            <div class="profile-view-bio">{{ $profileUser->bio }}</div>
                        @endif
                        <span class="role-badge role-{{ $profileUser->roles->first()?->name ?? 'user' }}">
                            {{ $profileUser->roles->first()?->name ?? 'user' }}
                        </span>
                        <div class="profile-view-meta">
                            Joined {{ $profileUser->created_at->format('d F Y') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
