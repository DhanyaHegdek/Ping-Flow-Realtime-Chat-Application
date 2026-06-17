<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Login extends Component
{
    public string $email    = '';
    public string $password = '';
    public string $error    = '';

    protected array $rules = [
        'email'    => 'required|email',
        'password' => 'required',
    ];

    public function submit(): void
    {
        $this->validate();
        $this->error = '';

        // Use 'web' guard — session based (not JWT 'api' guard)
        if (Auth::guard('web')->attempt(['email' => $this->email, 'password' => $this->password])) {
            session()->regenerate();
            $this->redirect('/', navigate: false);
            return;
        }

        $this->error = 'Invalid credentials. Please check your email and password.';
    }

    public function render(): View
    {
        return view('livewire.login')
            ->layout('layouts.auth', ['title' => 'Sign In']);
    }
}
