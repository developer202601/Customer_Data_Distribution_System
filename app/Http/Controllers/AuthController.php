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
            return redirect()->route('dashboard');
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

        $request->session()->regenerate();
        $request->session()->put('user', [
            'id' => $user->id,
            'username' => $user->username,
            'is_admin' => (bool) $user->admin_prev,
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
