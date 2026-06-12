<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon">⬡</span>
            <h1>Relayhub</h1>
        </div>
        <p class="auth-sub">Sign in to your account</p>

        @if($error)
            <div class="auth-error">{{ $error }}</div>
        @endif

        <form wire:submit.prevent="submit" class="auth-form">
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
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                />
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Sign In</span>
                <span wire:loading wire:target="submit">Signing in…</span>
            </button>
        </form>

        <p class="auth-footer">
            No account? <a href="{{ route('register') }}">Create one</a>
        </p>
    </div>
</div>
