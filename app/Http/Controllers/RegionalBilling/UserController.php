<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $users = User::where('system', 'rb')->orderBy('id')->get();
        return view('regionalbilling.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        if ($user->system !== 'rb') {
            abort(404);
        }

        return view('regionalbilling.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        if ($user->system !== 'rb') {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('rb.users.index')->with('status', 'User updated');
    }

    public function disable(User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        if ($user->system !== 'rb') {
            abort(404);
        }

        $user->status = 0;
        $user->save();

        return back()->with('status', 'User disabled');
    }

    public function enable(User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        if ($user->system !== 'rb') {
            abort(404);
        }

        $user->status = 1;
        $user->save();

        return back()->with('status', 'User enabled');
    }

    public function store(Request $request)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $request->validate([
            'username' => 'required|string|size:6|unique:users,username',
            'name' => 'nullable|string|max:255',
        ]);

        $user = new User();
        $user->username = $request->input('username');
        $user->name = $request->input('name');
        $user->system = 'rb';
        $user->status = 1;
        $user->supervisor = $sessionUser['id'] ?? null;
        $user->created_at = now();
        $user->save();

        return redirect()->route('rb.users.index')->with('status', 'User created');
    }

    public function destroy(User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        if ($user->system !== 'rb') {
            abort(404);
        }

        $user->delete();

        return redirect()->route('rb.users.index')->with('status', 'User deleted');
    }
}
