<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditProfilePanel extends Component
{
    use WithFileUploads;

    public string $name     = '';
    public string $email    = '';
    public string $bio      = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $editing = false;
    public bool $success = false;
    public string $error = '';

    public $avatarFile = null;       // uploaded file (temp)
    public bool $avatarUploading = false;

    protected $listeners = [
        'closeEditProfile' => 'close',
    ];

    public function mount(): void
    {
        $user = Auth::user();
        $this->name  = $user->name;
        $this->email = $user->email;
        $this->bio   = $user->bio ?? '';
    }

    public function startEditing(): void
    {
        $this->editing = true;
        $this->success = false;
        $this->error   = '';
    }

    public function cancel(): void
    {
        $user = Auth::user();
        $this->name  = $user->name;
        $this->email = $user->email;
        $this->bio   = $user->bio ?? '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->editing = false;
        $this->error   = '';
    }

    // Mirrors ProfileController::update()
    public function save(): void
    {
        $user = Auth::user();
        $this->error   = '';
        $this->success = false;

        $rules = [
            'name'  => 'required|string|max:255',
            'bio'   => 'nullable|string|max:500',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ];

        if ($this->password) {
            $rules['password'] = 'min:6|confirmed';
        }

        $this->validate($rules);

        $user->name  = $this->name;
        $user->bio   = $this->bio;
        $user->email = $this->email;

        if ($this->password) {
            $user->password = Hash::make($this->password);
        }

        $user->save();

        $this->password = '';
        $this->password_confirmation = '';
        $this->success = true;
        $this->editing = false;

        // Tell Chat component to refresh header (name shown in sidebar)
        $this->dispatch('profileUpdated');
    }

    // Mirrors ProfileController::uploadAvatar()
    public function updatedAvatarFile(): void
    {
        $this->validate([
            'avatarFile' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $this->avatarUploading = true;
        $user = Auth::user();

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $this->avatarFile->store('avatars', 'public');
        $user->avatar = $path;
        $user->save();

        $this->avatarFile = null;
        $this->avatarUploading = false;

        // Tell Chat component the avatar changed (refresh sidebar avatar)
        $this->dispatch('profileUpdated');
    }

    public function close(): void
    {
        $this->dispatch('closeEditProfilePanel');
    }

    public function render()
    {
        $user = Auth::user();
        $role = $user->getRoleNames()->first() ?? 'user';

        return view('livewire.edit-profile-panel', [
            'user' => $user,
            'role' => $role,
        ]);
    }
}
