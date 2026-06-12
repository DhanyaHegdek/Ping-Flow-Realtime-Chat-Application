<?php

namespace App\Livewire;

use App\Events\RoleChanged;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UsersPage extends Component
{
    public array  $users         = [];
    public string $search        = '';
    public bool   $showCreate    = false;
    public ?int   $viewProfileId = null;

    // Create form
    public string $newName     = '';
    public string $newEmail    = '';
    public string $newPassword = '';
    public string $newRole     = 'user';

    public function mount(): void
    {
        $this->loadUsers();
    }

    // Mirrors AdminController::listUsers()
    public function loadUsers(): void
    {
        $this->users = User::with('roles')
            ->select('id', 'name', 'email', 'avatar', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'avatar'     => $u->avatar,
                'role'       => $u->roles->first()?->name ?? 'user',
                'created_at' => $u->created_at->toISOString(),
            ])
            ->toArray();
    }

    public function getFilteredUsersProperty(): array
    {
        if (!$this->search) return $this->users;
        $q = strtolower($this->search);
        return array_values(array_filter($this->users, fn($u) =>
            str_contains(strtolower($u['name']), $q) ||
            str_contains(strtolower($u['email']), $q)
        ));
    }

    // Mirrors AdminController::createUser()
    public function createUser(): void
    {
        $this->validate([
            'newName'     => 'required|string|max:255',
            'newEmail'    => 'required|email|unique:users,email',
            'newPassword' => 'required|min:6',
            'newRole'     => 'required|in:admin,user',
        ]);

        $user = User::create([
            'name'     => $this->newName,
            'email'    => $this->newEmail,
            'password' => Hash::make($this->newPassword),
        ]);

        $user->assignRole($this->newRole);

        $this->reset('newName', 'newEmail', 'newPassword', 'showCreate');
        $this->newRole = 'user';
        $this->loadUsers();
    }

    // Mirrors AdminController::changeRole() — also broadcasts RoleChanged
    public function changeRole(int $userId, string $currentRole): void
    {
        if ($userId === Auth::id()) return; // cannot change own role

        $newRole = $currentRole === 'admin' ? 'user' : 'admin';

        $user = User::findOrFail($userId);
        $user->syncRoles([$newRole]);

        // Same broadcast as AdminController
        broadcast(new RoleChanged($user->id, $newRole));

        $this->loadUsers();
    }

    // Mirrors AdminController::deleteUser()
    public function deleteUser(int $userId): void
    {
        if ($userId === Auth::id()) return; // cannot delete yourself

        User::findOrFail($userId)->delete();
        $this->loadUsers();
    }

    public function render()
    {
        $profileUser = $this->viewProfileId
            ? User::with('roles')->find($this->viewProfileId)
            : null;

        return view('livewire.users-page', [
            'filteredUsers' => $this->filteredUsers,
            'profileUser'   => $profileUser,
            'totalCount'    => count($this->users),
        ])->layout('layouts.app');
    }
}
