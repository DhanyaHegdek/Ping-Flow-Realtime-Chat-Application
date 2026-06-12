<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon">⬡</span>
            <h1>Relayhub</h1>
        </div>
        <p class="auth-sub">Create your account</p>

        @if($error)
            <div class="auth-error">{{ $error }}</div>
        @endif

        <form wire:submit.prevent="submit" class="auth-form">
            <div class="field">
                <label>Name</label>
                <input
                    type="text"
                    wire:model.defer="name"
                    placeholder="Your name"
                    required
                    autocomplete="name"
                />
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Email</label>
                <input
                    type="email"
                    wire:model.defer="email"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                />
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Password</label>
                <input
                    type="password"
                    wire:model.defer="password"
                    placeholder="Min. 6 characters"
                    minlength="6"
                    required
                    autocomplete="new-password"
                />
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Create Account</span>
                <span wire:loading wire:target="submit">Creating account…</span>
            </button>
        </form>

        <p class="auth-footer">
            Already have an account? <a href="{{ route('login') }}">Sign in</a>
        </p>
    </div>
</div>
