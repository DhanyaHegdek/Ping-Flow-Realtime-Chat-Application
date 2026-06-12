<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Register extends Component
{
    public string $name     = '';
    public string $email    = '';
    public string $password = '';
    public string $error    = '';

    protected array $rules = [
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|min:6',
    ];

    protected array $messages = [
        'email.unique' => 'This email is already registered.',
    ];

    public function submit(): void
    {
        $this->validate();

        $user = User::create([
            'name'     => $this->name,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
        ]);

        // Mirror AuthController::register() — every self-registered user gets 'user' role
        $user->assignRole('user');

        Auth::guard('web')->login($user);
        session()->regenerate();

        $this->redirect('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.register')
            ->layout('layouts.auth', ['title' => 'Create Account']);
    }
}
