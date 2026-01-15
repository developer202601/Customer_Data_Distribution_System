<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('user')) {
            $sessionUser = $request->session()->get('user');

            $targetRoute = ($sessionUser['system'] ?? null) === 'cc'
                ? ((($sessionUser['is_admin'] ?? false) && ($sessionUser['assignment'] ?? null) !== 'super') ? 'cc.users.index' : 'cc.dashboard')
                : 'dashboard';

            return redirect()->route($targetRoute);
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'digits:6'],
        ]);

        $user = User::where('username', $validated['username'])->first();

        if (!$user) {
            return back()
                ->withErrors(['username' => 'Invalid username.'])
                ->withInput();
        }

        if (!$user->status) {
            return back()
                ->withErrors(['username' => 'This user is disabled.'])
                ->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('user', [
            'id' => $user->id,
            'username' => $user->username,
            'is_admin' => (bool) $user->admin_prev,
            'system' => $user->system,
            'assignment' => $user->assignment ?? null,
            'name' => $user->name ?? null,
        ]);

        $targetRoute = match ($user->system) {
            'cc' => ($user->admin_prev && $user->assignment !== 'super') ? 'cc.users.index' : 'cc.dashboard',
            default => 'dashboard',
        };

        return redirect()->route($targetRoute);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
