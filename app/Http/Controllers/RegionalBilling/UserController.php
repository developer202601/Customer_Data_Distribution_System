<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private function ensureRbAdminContext(): array
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            abort(403);
        }

        $assignment = (string) ($sessionUser['assignment'] ?? '');
        $isSuper = $assignment === 'super';
        $isRtomAdmin = str_starts_with($assignment, 'rtom_');

        if (! $isSuper && ! $isRtomAdmin) {
            abort(403);
        }

        return [
            'sessionUser' => $sessionUser,
            'isSuper' => $isSuper,
            'isRtomAdmin' => $isRtomAdmin,
            'assignment' => $assignment,
            'rtomSlug' => $isRtomAdmin ? Str::after($assignment, 'rtom_') : null,
        ];
    }

    public function index(Request $request)
    {
        $ctx = $this->ensureRbAdminContext();
        $sessionUser = $ctx['sessionUser'];

        $users = User::where('system', 'rb')
            ->when($ctx['isRtomAdmin'], function ($query) use ($sessionUser) {
                $query->where('assignment', 'like', 'caller_%')
                    ->where('supervisor', $sessionUser['id'] ?? null);
            })
            ->orderBy('id')
            ->get();

        $scopeLabel = $ctx['isRtomAdmin'] ? 'Caller Management' : 'User Management';
        return view('regionalbilling.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $ctx = $this->ensureRbAdminContext();

        if ($user->system !== 'rb') {
            abort(404);
        }

        if ($ctx['isRtomAdmin']) {
            if (! str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) ($ctx['sessionUser']['id'] ?? 0)) {
                abort(404);
            }
        }

        return view('regionalbilling.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $ctx = $this->ensureRbAdminContext();

        if ($user->system !== 'rb') {
            abort(404);
        }

        if ($ctx['isRtomAdmin']) {
            if (! str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) ($ctx['sessionUser']['id'] ?? 0)) {
                abort(404);
            }
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
        $ctx = $this->ensureRbAdminContext();

        if ($user->system !== 'rb') {
            abort(404);
        }

        if ($ctx['isRtomAdmin']) {
            if (! str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) ($ctx['sessionUser']['id'] ?? 0)) {
                abort(404);
            }
        }

        $user->status = 0;
        $user->save();

        return back()->with('status', 'User disabled');
    }

    public function enable(User $user)
    {
        $ctx = $this->ensureRbAdminContext();

        if ($user->system !== 'rb') {
            abort(404);
        }

        if ($ctx['isRtomAdmin']) {
            if (! str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) ($ctx['sessionUser']['id'] ?? 0)) {
                abort(404);
            }
        }

        $user->status = 1;
        $user->save();

        return back()->with('status', 'User enabled');
    }

    public function store(Request $request)
    {
        $ctx = $this->ensureRbAdminContext();
        $sessionUser = $ctx['sessionUser'];

        $request->validate([
            'username' => 'required|string|size:6|unique:users,username',
            'name' => 'nullable|string|max:255',
        ]);

        $user = new User();
        $user->username = $request->input('username');
        $user->name = $request->input('name');
        $user->system = 'rb';
        $user->admin_prev = $ctx['isSuper'] ? 1 : 0;
        $user->status = 1;
        $user->supervisor = $sessionUser['id'] ?? null;
        if ($ctx['isRtomAdmin']) {
            $user->assignment = 'caller_' . ($ctx['rtomSlug'] ?? '');
        }
        $user->created_at = now();
        $user->save();

        return redirect()->route('rb.users.index')->with('status', 'User created');
    }

    public function destroy(User $user)
    {
        $ctx = $this->ensureRbAdminContext();

        if ($user->system !== 'rb') {
            abort(404);
        }

        if ($ctx['isRtomAdmin']) {
            if (! str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) ($ctx['sessionUser']['id'] ?? 0)) {
                abort(404);
            }
        }

        $user->delete();

        return redirect()->route('rb.users.index')->with('status', 'User deleted');
    }
}

