<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Events\RoleChanged;

class AdminController extends Controller
{
    //GET /api/admin/users        List all users with their roles

    public function listUsers()
    {
        $users = User::with('roles')
            ->select('id', 'name', 'email', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'role'       => $user->roles->first()?->name ?? 'user',
                    'created_at' => $user->created_at,
                ];
            });

        return response()->json($users);
    }

    //GET /api/admin/users/{id}    View a specific user's profile
     
    public function viewProfile($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->roles->first()?->name ?? 'user',
            'created_at' => $user->created_at,
        ]);
    }

    //POST /api/admin/users Create a new user and assign a role
     
    public function createUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'     => 'required|in:admin,user',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $request->role,
        ], 201);
    }

    // DELETE /api/admin/users/{id} Delete a user (cannot delete yourself)

    public function deleteUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot delete yourself.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    //PUT /api/admin/users/{id}/role Change a user's role (promote to admin or demote to user)
     

    public function changeRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:admin,user',
        ]);

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot change your own role.'], 403);
        }

        $user->syncRoles([$request->role]);

        // Broadcast role change to that user instantly
        broadcast(new RoleChanged($user->id, $request->role));

        return response()->json([
            'message' => "Role updated to {$request->role} successfully.",
            'user' => [
                'id'   => $user->id,
                'name' => $user->name,
                'role' => $request->role,
            ],
        ]);
    }
}