<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $region = session('user')['assignment'] ?? null;
        $regionAdminId = session('user')['id'] ?? null;

        if (!$region || in_array($region, ['super'], true)) {
            abort(403);
        }

        $status = $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        $usersQuery = User::where('system', 'cc')
            ->where('assignment', 'like', 'caller_%')
            ->where('supervisor', $regionAdminId)
            ->withCount(['supervisedUsers', 'interactionsAsAgent', 'rowAssignments']);

        if ($status === 'active') {
            $usersQuery->where('status', 1);
        } elseif ($status === 'disabled') {
            $usersQuery->where('status', 0);
        }

        if ($q !== '') {
            $usersQuery->where(function ($query) use ($q) {
                $query->where('username', 'like', '%' . $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            });
        }

        $users = $usersQuery->orderBy('username')->get();

        return view('callcenter.users.index', [
            'users' => $users,
            'filter_status' => $status,
            'filter_q' => $q,
        ]);
    }

    public function edit(User $user): View
    {
        $regionAdminId = session('user')['id'] ?? null;

        if ($user->system !== 'cc' || !str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) $regionAdminId) {
            abort(404);
        }

        return view('callcenter.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $regionAdminId = session('user')['id'] ?? null;

        if ($user->system !== 'cc' || !str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) $regionAdminId) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('cc.users.index')->with('status', 'User updated successfully.');
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        $regionAdminId = session('user')['id'] ?? null;

        if ($user->system !== 'cc' || !str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) $regionAdminId) {
            abort(404);
        }

        if (! $user->status) {
            return redirect()->route('cc.users.index')->with('status', 'User is already disabled.');
        }

        $user->status = 0;
        $user->save();

        return redirect()->route('cc.users.index')->with('status', 'User disabled successfully.');
    }

    public function enable(Request $request, User $user): RedirectResponse
    {
        $regionAdminId = session('user')['id'] ?? null;

        if ($user->system !== 'cc' || !str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) $regionAdminId) {
            abort(404);
        }

        if ($user->status) {
            return redirect()->route('cc.users.index')->with('status', 'User is already enabled.');
        }

        $user->status = 1;
        $user->save();

        return redirect()->route('cc.users.index')->with('status', 'User enabled successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $regionAdminId = session('user')['id'] ?? null;

        if ($user->system !== 'cc' || !str_starts_with((string) $user->assignment, 'caller_') || (int) $user->supervisor !== (int) $regionAdminId) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.users.index')->withErrors([
                'delete' => 'This user is fixed and cannot be deleted. Disable the user instead.',
            ]);
        }

        if ($user->supervisedUsers()->exists() || $user->interactionsAsAgent()->exists() || $user->rowAssignments()->exists()) {
            return redirect()->route('cc.users.index')->withErrors([
                'delete' => 'This user has related records and cannot be deleted. Disable the user instead.',
            ]);
        }

        $user->delete();

        return redirect()->route('cc.users.index')->with('status', 'User deleted successfully.');
    }
}
