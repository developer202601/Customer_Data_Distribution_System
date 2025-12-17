<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenter\CallCenterUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = CallCenterUser::orderBy('username')->get();

        return view('callcenter.users.index', [
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'digits:6', 'unique:users,username,NULL,id,system,cc'],
            'admin_prev' => ['sometimes', 'boolean'],
        ]);

        CallCenterUser::create([
            'username' => $validated['username'],
            'admin_prev' => $request->boolean('admin_prev'),
            'status' => 1,
            'system' => 'cc',
            'fixed' => 0,
            'created_at' => now(),
        ]);

        return redirect()->route('cc.users.index')->with('status', 'User created successfully.');
    }

    public function edit(CallCenterUser $ccUser): View
    {
        return view('callcenter.users.edit', [
            'user' => $ccUser,
        ]);
    }

    public function update(Request $request, CallCenterUser $ccUser): RedirectResponse
    {
        $rules = [
            'admin_prev' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
        ];

        // allow username edit only when user is not fixed
        if (!$ccUser->fixed) {
            $rules['username'] = ['required', 'digits:6', "unique:users,username,{$ccUser->id},id,system,cc"];
        }

        // Ensure admins cannot update the `name` field here even if it's submitted.
        $input = $request->except('name');

        $validated = \Illuminate\Support\Facades\Validator::make($input, $rules)->validate();

        if (!$ccUser->fixed && isset($validated['username'])) {
            $ccUser->username = $validated['username'];
        }

        $ccUser->admin_prev = isset($validated['admin_prev']) ? (bool)$validated['admin_prev'] : $request->boolean('admin_prev');
        $ccUser->status = isset($validated['status']) ? (bool)$validated['status'] : $request->boolean('status');
        $ccUser->save();

        return redirect()->route('cc.users.index')->with('status', 'User updated successfully.');
    }

    public function disable(CallCenterUser $ccUser): RedirectResponse
    {
        if (!$ccUser->status) {
            return redirect()->route('cc.users.index')->with('status', 'User is already disabled.');
        }

        $ccUser->status = 0;
        $ccUser->save();

        return redirect()->route('cc.users.index')->with('status', 'User disabled successfully.');
    }

    public function destroy(CallCenterUser $ccUser): RedirectResponse
    {
        if ($ccUser->fixed) {
            return redirect()->route('cc.users.index')->withErrors([
                'delete' => 'This user is fixed and cannot be deleted. Disable the user instead.',
            ]);
        }

        $ccUser->delete();

        return redirect()->route('cc.users.index')->with('status', 'User deleted successfully.');
    }

    /**
     * Set the current caller's display name (used for first-login flow).
     */
    public function setName(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $sessionUser = session('user');
        if (!$sessionUser || empty($sessionUser['id'])) {
            return response()->json(['error' => 'Not authenticated'], 403);
        }

        $user = CallCenterUser::find($sessionUser['id']);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->name = $validated['name'];
        $user->save();

        // update session copy
        $sessionUser['name'] = $user->name;
        session(['user' => $sessionUser]);

        return response()->json(['success' => true, 'name' => $user->name]);
    }
}
