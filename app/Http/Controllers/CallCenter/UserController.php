<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenter\CallCenterUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $status = $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role', 'all');

        $users = $this->buildUsersQuery($status, $q, $role)->orderBy('username')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('callcenter.users._rows', ['users' => $users])->render(),
                'count' => $users->count(),
            ]);
        }

        return view('callcenter.users.index', [
            'users' => $users,
            'filter_status' => $status,
            'filter_q' => $q,
            'filter_role' => $role,
        ]);
    }

    protected function buildUsersQuery(string $status, string $q, string $role)
    {
        $usersQ = CallCenterUser::query();
        $usersQ->with('supervisorUser');
        // If logged-in user is a supervisor, show callers for the supervisor's RTOM (exclude supervisor accounts)
        if (\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_')) {
            $assign = session('user.assignment') ?? '';
            $rtomPart = preg_replace('/^supervisor_/', '', $assign);
            $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
            if ($rtomVal !== '') {
                $usersQ->where('assignment', 'caller_rtom_' . $rtomVal);
            } else {
                // fallback: show callers created by this supervisor
                $usersQ->where('supervisor', session('user')['id'] ?? null);
            }
        }

        // Status filter
        if ($status === 'active') {
            $usersQ->where('status', true);
        } elseif ($status === 'disabled') {
            $usersQ->where('status', false);
        }

        // Role filter
        if ($role === 'super_admin') {
            $usersQ->where('assignment', 'super');
        } elseif ($role === 'region_admin') {
            $usersQ->where('assignment', 'like', 'REGION%');
        } elseif ($role === 'rtom_admin') {
            $usersQ->where('assignment', 'like', 'rtom_%');
        } elseif ($role === 'supervisor') {
            $usersQ->where('assignment', 'like', 'supervisor_%');
        } elseif ($role === 'caller') {
            $usersQ->where('assignment', 'like', 'caller_%');
        }

        // Search filter
        if ($q !== '') {
            $usersQ->where(function ($query) use ($q) {
                $query->where('username', 'like', '%' . $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            });
        }

        return $usersQ;
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'digits:6', 'unique:users,username,NULL,id,system,cc'],
            'admin_prev' => ['sometimes', 'boolean'],
        ]);

        $sessionUser = session('user');
        $assignment = 'super'; // default for admins
        $adminPrev = $request->boolean('admin_prev');

        if (\Illuminate\Support\Str::startsWith($sessionUser['assignment'] ?? '', 'supervisor_')) {
            // Supervisors create callers
            $assign = $sessionUser['assignment'] ?? '';
            $rtomPart = preg_replace('/^supervisor_/', '', $assign);
            $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
            $assignment = 'caller_rtom_' . $rtomVal;
            $adminPrev = false; // callers are not admins
        }

        CallCenterUser::create([
            'username' => $validated['username'],
            'admin_prev' => $adminPrev,
            'status' => 1,
            'system' => 'cc',
            'fixed' => 0,
            'created_at' => now(),
            'supervisor' => $sessionUser['id'] ?? null,
            'assignment' => $assignment,
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

        // allow username edit only when user is not fixed and is disabled
        if (!$ccUser->fixed && !$ccUser->status) {
            $rules['username'] = ['required', 'digits:6', "unique:users,username,{$ccUser->id},id,system,cc"];
        }

        // Ensure admins cannot update the `name` field here even if it's submitted.
        $input = $request->except('name');

        $validated = \Illuminate\Support\Facades\Validator::make($input, $rules)->validate();

        // Only allow username change when the user is disabled and not fixed
        $oldUsername = $ccUser->username;
        if (!$ccUser->fixed && isset($validated['username']) && !$ccUser->status) {
            $ccUser->username = $validated['username'];
        }

        $ccUser->admin_prev = isset($validated['admin_prev']) ? (bool)$validated['admin_prev'] : $request->boolean('admin_prev');
        $ccUser->status = isset($validated['status']) ? (bool)$validated['status'] : $request->boolean('status');
        $ccUser->save();
        // If the username changed, terminate any active sessions for that user
        if ($oldUsername !== $ccUser->username) {
            try {
                \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $ccUser->id)->delete();
            } catch (\Exception $e) {
                // ignore failures to delete sessions
            }
        }

        return redirect()->route(session('user.assignment') === 'super' ? 'cc.super.regions' : 'cc.users.index')->with('status', 'User updated successfully.');
    }

    public function disable(Request $request, CallCenterUser $ccUser): RedirectResponse
    {
        if (!$ccUser->status) {
            $return = $request->input('return_to');
            if ($return) return redirect($return)->with('status', 'User is already disabled.');
            return redirect()->route('cc.users.index')->with('status', 'User is already disabled.');
        }

        $ccUser->status = 0;
        $ccUser->save();

        $return = $request->input('return_to');
        if ($return) return redirect($return)->with('status', 'User disabled successfully.');

        return redirect()->route('cc.users.index')->with('status', 'User disabled successfully.');
    }

    public function enable(Request $request, CallCenterUser $ccUser): RedirectResponse
    {
        if ($ccUser->status) {
            $return = $request->input('return_to');
            if ($return) return redirect($return)->with('status', 'User is already enabled.');
            return redirect()->route('cc.users.index')->with('status', 'User is already enabled.');
        }

        $ccUser->status = 1;
        $ccUser->save();

        $return = $request->input('return_to');
        if ($return) return redirect($return)->with('status', 'User enabled successfully.');

        return redirect()->route('cc.users.index')->with('status', 'User enabled successfully.');
    }

    public function destroy(CallCenterUser $ccUser): RedirectResponse
    {
        if ($ccUser->fixed) {
            return redirect()->route('cc.users.index')->withErrors([
                'delete' => 'This user is fixed and cannot be deleted. Disable the user instead.',
            ]);
        }

        $ccUser->delete();
        $return = request()->input('return_to');
        if ($return) {
            return redirect($return)->with('status', 'User deleted successfully.');
        }

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
