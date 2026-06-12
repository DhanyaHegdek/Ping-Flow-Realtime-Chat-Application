<div class="ep-panel">
    <div class="ep-header">
        <span class="ep-title">My Profile</span>
        <button class="ep-close" wire:click="close">✕</button>
    </div>

    <div class="ep-body">
        {{-- Avatar section --}}
        <div class="ep-avatar-section">
            <div
                class="ep-avatar"
                x-data="{ uploading: @entangle('avatarUploading') }"
                @click="$refs.avatarInput.click()"
                title="Click to change photo"
                style="
                    width:72px; height:72px; border-radius:50%;
                    background: {{ $user->avatar ? 'transparent' : 'hsl(' . (array_sum(array_map('ord', str_split($name ?: $user->name))) % 360) . ',50%,55%)' }};
                    display:flex; align-items:center; justify-content:center;
                    font-weight:700; color:#fff; font-size:26px;
                    flex-shrink:0; cursor:pointer; position:relative; overflow:hidden;
                "
            >
                @if($user->avatar)
                    <img
                        src="{{ asset('storage/' . $user->avatar) }}"
                        alt="avatar"
                        style="width:100%;height:100%;object-fit:cover;"
                    />
                @else
                    {{-- Initials --}}
                    @php
                        $words = array_filter(explode(' ', $name ?: $user->name));
                        $initials = strtoupper(implode('', array_map(fn($w) => substr($w,0,1), $words)));
                        $initials = substr($initials, 0, 2) ?: '?';
                    @endphp
                    {{ $initials }}
                @endif

                {{-- Hover / uploading overlay --}}
                <div
                    class="ep-avatar-overlay"
                    x-show="uploading"
                    style="
                        position:absolute; inset:0; background:rgba(0,0,0,0.4);
                        display:flex; align-items:center; justify-content:center;
                        font-size:20px;
                    "
                >⏳</div>
                <div
                    class="ep-avatar-overlay ep-avatar-overlay-hover"
                    style="
                        position:absolute; inset:0; background:rgba(0,0,0,0.4);
                        display:flex; align-items:center; justify-content:center;
                        font-size:20px; opacity:0; transition:opacity 0.2s;
                    "
                >📷</div>
            </div>

            <input
                type="file"
                x-ref="avatarInput"
                wire:model="avatarFile"
                accept="image/jpg,image/jpeg,image/png,image/webp"
                style="display:none"
            />

            <div class="ep-role-badge ep-role-{{ $role }}">{{ $role }}</div>
        </div>

        @if($success)
            <div class="ep-success">✅ Profile updated!</div>
        @endif
        @if($error)
            <div class="ep-error">{{ $error }}</div>
        @endif
        @error('avatarFile')
            <div class="ep-error">{{ $message }}</div>
        @enderror

        {{-- Fields --}}
        <div class="ep-fields">
            <div class="ep-field">
                <label>Name</label>
                @if($editing)
                    <input type="text" wire:model.defer="name" class="ep-input" />
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                @else
                    <div class="ep-value">{{ $user->name }}</div>
                @endif
            </div>

            <div class="ep-field">
                <label>Email</label>
                @if($editing)
                    <input type="email" wire:model.defer="email" class="ep-input" />
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                @else
                    <div class="ep-value">{{ $user->email }}</div>
                @endif
            </div>

            <div class="ep-field">
                <label>Bio</label>
                @if($editing)
                    <textarea
                        wire:model.defer="bio"
                        class="ep-input ep-textarea"
                        placeholder="Tell something about yourself…"
                        rows="3"
                        maxlength="500"
                    ></textarea>
                    @error('bio') <span class="field-error">{{ $message }}</span> @enderror
                @else
                    <div class="ep-value ep-bio">
                        @if($user->bio)
                            {{ $user->bio }}
                        @else
                            <span class="ep-empty-bio">No bio yet</span>
                        @endif
                    </div>
                @endif
            </div>

            @if($editing)
                <div class="ep-field">
                    <label>New Password <span class="ep-optional">(leave blank to keep current)</span></label>
                    <input type="password" wire:model.defer="password" class="ep-input" placeholder="Min. 6 characters" />
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="ep-field">
                    <label>Confirm Password</label>
                    <input type="password" wire:model.defer="password_confirmation" class="ep-input" placeholder="Repeat new password" />
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="ep-actions">
            @if($editing)
                <button class="ep-btn-cancel" wire:click="cancel">Cancel</button>
                <button class="ep-btn-save" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            @else
                <button class="ep-btn-edit" wire:click="startEditing">✏️ Edit Profile</button>
            @endif
        </div>
    </div>
</div>

<script>
    // Hover effect for avatar overlay (mirrors React onMouseEnter/Leave)
    document.addEventListener('DOMContentLoaded', () => {
        const avatar = document.querySelector('.ep-avatar');
        const hoverOverlay = document.querySelector('.ep-avatar-overlay-hover');
        if (avatar && hoverOverlay) {
            avatar.addEventListener('mouseenter', () => hoverOverlay.style.opacity = '1');
            avatar.addEventListener('mouseleave', () => hoverOverlay.style.opacity = '0');
        }
    });
</script>
