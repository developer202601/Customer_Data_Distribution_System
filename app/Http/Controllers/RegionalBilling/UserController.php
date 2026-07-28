<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\MasterDatasetRow;
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

    private function getSharedRtomSupervisorIds(): array
    {
        $sessionUser = session('user');
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        if ($currentUserId <= 0) {
            return [];
        }

        $assignment = strtolower(trim((string) ($sessionUser['assignment'] ?? '')));
        if (! str_starts_with($assignment, 'rtom_')) {
            return [$currentUserId];
        }

        $dbUser = User::find($currentUserId);
        $region = $dbUser ? $this->getRtomAdminRegion($dbUser) : null;
        if (! $region) {
            return [$currentUserId];
        }

        $rtomValue = substr($assignment, 5);
        $sharedUsers = User::where('system', 'rb')
            ->where('status', 1)
            ->where('assignment', 'rtom_' . $rtomValue)
            ->get();

        $ids = [$currentUserId];
        foreach ($sharedUsers as $user) {
            if ($this->getRtomAdminRegion($user) === $region && $user->id !== $currentUserId) {
                $ids[] = (int) $user->id;
            }
        }

        return $ids;
    }

    protected function getRtomAdminRegion(User $user): ?string
    {
        $currentUser = $user->supervisor ? User::find($user->supervisor) : null;
        while ($currentUser) {
            $currentAssignment = $currentUser->assignment ?? null;
            if ($currentAssignment && !str_starts_with($currentAssignment, 'caller_') && !str_starts_with($currentAssignment, 'rtom_') && !str_starts_with($currentAssignment, 'supervisor_') && $currentAssignment !== 'super') {
                return $currentAssignment;
            }
            $currentUser = $currentUser->supervisor ? User::find($currentUser->supervisor) : null;
        }

        $assignment = strtolower(trim((string) $user->assignment));
        $rtomValue = preg_replace('/^rtom_/', '', $assignment);
        return $rtomValue ? $this->deriveRegionFromRtom($rtomValue) : null;
    }

    protected function deriveRegionFromRtom(string $rtomValue): ?string
    {
        $query = MasterDatasetRow::query();

        $latestProcessId = MasterDatasetRow::query()->max('process_id');
        if ($latestProcessId) {
            $query->where('process_id', $latestProcessId);
        }

        $region = $query
            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->value('region');

        if (! $region) {
            $region = MasterDatasetRow::query()
                ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
                ->whereNotNull('region')
                ->where('region', '<>', '')
                ->value('region');
        }

        return $region;
    }

    private function isCallerSharedByRtomAdmin(User $user): bool
    {
        if ($user->system !== 'rb' || ! str_starts_with((string) ($user->assignment ?? ''), 'caller_')) {
            return false;
        }

        $supervisorIds = $this->getSharedRtomSupervisorIds();
        return in_array((int) ($user->supervisor ?? 0), $supervisorIds, true);
    }

    public function index(Request $request)
    {
        $ctx = $this->ensureRbAdminContext();
        $sessionUser = $ctx['sessionUser'];

        $users = User::where('system', 'rb')
            ->when($ctx['isRtomAdmin'], function ($query) use ($sessionUser) {
                $query->where('assignment', 'like', 'caller_%');
                $supervisorIds = $this->getSharedRtomSupervisorIds();
                $query->whereIn('supervisor', $supervisorIds);
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
            if (! $this->isCallerSharedByRtomAdmin($user)) {
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
            if (! $this->isCallerSharedByRtomAdmin($user)) {
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
            if (! $this->isCallerSharedByRtomAdmin($user)) {
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
            if (! $this->isCallerSharedByRtomAdmin($user)) {
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
            if (! $this->isCallerSharedByRtomAdmin($user)) {
                abort(404);
            }
        }

        $user->delete();

        return redirect()->route('rb.users.index')->with('status', 'User deleted');
    }
}

