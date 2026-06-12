<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Every self-registered user gets the 'user' role
        $user->assignRole('user');

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        
        //         Why attempt() instead of manual lookup?
        // attempt() handles everything — finding the user, checking the password hash, generating the token — all in one call. It also fires Laravel's login events which are useful for logging and audit trails later.
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json(['token' => $token]);
    }

    public function logout()
    {
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Logged out']);
    }

    public function me()
{
    $user = Auth::guard('api')->user()->load('roles'); 
    return response()->json($this->formatUser($user));
}

    //Format user with role included — React needs this to know whether to show the Admin panel or not
     
    private function formatUser(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->roles->first()?->name ?? 'user',
            'avatar' => $user->avatar,
        ];
    }
}