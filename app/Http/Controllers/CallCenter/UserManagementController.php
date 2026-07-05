<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MasterDatasetRow;
use App\Models\CallCenter\CallCenterUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Get the roles the current user is allowed to assign.
     */
    protected function getAllowedRoles(): array
    {
        $sessionUser = session('user');
        $assignment = $sessionUser['assignment'] ?? '';

        if ($assignment === 'super') {
            return [
                'super'      => 'Super Admin',
                'region'     => 'Region Admin',
                'rtom_admin' => 'RTOM Admin',
                'supervisor' => 'Supervisor',
                'caller'     => 'Caller',
            ];
        }

        // Region Admin – assignment is a plain region name (e.g. "REGION ABC")
        if ($assignment
            && ! str_starts_with($assignment, 'rtom_')
            && ! str_starts_with($assignment, 'supervisor_')
            && ! str_starts_with($assignment, 'caller_')
            && $assignment !== 'super'
        ) {
            return [
                'rtom_admin' => 'RTOM Admin',
                'supervisor' => 'Supervisor',
            ];
        }

        if (str_starts_with($assignment, 'rtom_')) {
            return [
                'supervisor' => 'Supervisor',
            ];
        }

        if (str_starts_with($assignment, 'supervisor_')) {
            return [
                'caller' => 'Caller',
            ];
        }

        return [];
    }

    /**
     * Get the current user's region name based on their assignment.
     */
    protected function currentRegion(): ?string
    {
        $assignment = session('user.assignment') ?? '';

        if ($assignment === 'super') {
            return null; // Super admin sees all
        }

        // Region admin: assignment IS the region name
        if ($assignment && ! str_starts_with($assignment, 'rtom_')
            && ! str_starts_with($assignment, 'supervisor_')
            && ! str_starts_with($assignment, 'caller_')
        ) {
            return $assignment;
        }

        // Extract region from rtom_/supervisor_/caller_ assignment
        // Format: {role}_rtom_RTOMNAME → we need the region from master data
        // But for simplicity, we'll use master data to find the region for this RTOM
        $rtom = $this->currentRtom();
        if ($rtom) {
            $lastTwo = MasterDatasetRow::select('process_id')
                ->distinct()
                ->orderBy('process_id', 'desc')
                ->limit(2)
                ->pluck('process_id')
                ->toArray();

            if (! empty($lastTwo)) {
                $region = MasterDatasetRow::whereIn('process_id', $lastTwo)
                    ->where('rtom', $rtom)
                    ->whereNotNull('region')
                    ->value('region');
                return $region;
            }
        }

        return null;
    }

    /**
     * Get the current user's RTOM name.
     */
    protected function currentRtom(): ?string
    {
        $assignment = session('user.assignment') ?? '';

        // rtom_rtom_NAME
        if (str_starts_with($assignment, 'rtom_')) {
            return str_replace('rtom_', '', $assignment);
        }

        // supervisor_rtom_NAME
        if (str_starts_with($assignment, 'supervisor_')) {
            return str_replace('supervisor_rtom_', '', $assignment);
        }

        // caller_rtom_NAME
        if (str_starts_with($assignment, 'caller_')) {
            return str_replace('caller_rtom_', '', $assignment);
        }

        return null;
    }

    /**
     * Get available RTOMs for the current user's scope.
     */
    protected function availableRtoms(): array
    {
        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        if (empty($lastTwo)) {
            return [];
        }

        $query = MasterDatasetRow::whereIn('process_id', $lastTwo)
            ->whereNotNull('rtom');

        $region = $this->currentRegion();
        if ($region) {
            $query->where('region', $region);
        }

        return $query->distinct()->pluck('rtom')->values()->toArray();
    }

    /**
     * Get available regions for the current user's scope.
     */
    protected function availableRegions(): array
    {
        $sessionUser = session('user');
        $assignment = $sessionUser['assignment'] ?? '';

        // Super admin sees all regions from last 2 reports
        if ($assignment === 'super') {
            $lastTwo = MasterDatasetRow::select('process_id')
                ->distinct()
                ->orderBy('process_id', 'desc')
                ->limit(2)
                ->pluck('process_id')
                ->toArray();

            if (empty($lastTwo)) {
                return [];
            }

            return MasterDatasetRow::whereIn('process_id', $lastTwo)
                ->whereNotNull('region')
                ->distinct()
                ->pluck('region')
                ->values()
                ->toArray();
        }

        // Region admin: only their own region
        $region = $this->currentRegion();
        return $region ? [$region] : [];
    }

    /**
     * Get eligible supervisors for role assignment.
     * For a given RTOM, returns all supervisors assigned to that RTOM.
     */
    protected function availableSupervisors(?string $rtom = null): array
    {
        $query = User::where('system', 'cc')
            ->where('assignment', 'like', 'supervisor_rtom_%')
            ->where('status', true);

        if ($rtom) {
            $query->where('assignment', 'supervisor_rtom_' . strtolower(str_replace(' ', '_', $rtom)));
        }

        return $query->orderBy('username')->get(['id', 'username', 'name'])->toArray();
    }

    /**
     * Get eligible RTOM admins for role assignment.
     */
    protected function availableRtomAdmins(): array
    {
        $query = User::where('system', 'cc')
            ->where('assignment', 'like', 'rtom_%')
            ->where('admin_prev', true)
            ->where('status', true);

        $region = $this->currentRegion();
        if ($region) {
            $query->where('supervisor', session('user')['id'] ?? null);
        }

        return $query->orderBy('username')->get(['id', 'username', 'name', 'assignment'])->toArray();
    }

    /**
     * Display the user management index.
     */
    public function index(Request $request): View
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        $allowedRoles = $this->getAllowedRoles();
        if (empty($allowedRoles)) {
            abort(403, 'You do not have permission to manage users.');
        }

        $status = $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role', 'all');

        $users = $this->buildFilteredQuery($status, $q, $role)->orderBy('username')->get();

        return view('cc.management.index', [
            'users'        => $users,
            'allowedRoles' => $allowedRoles,
            'filter_status' => $status,
            'filter_q'     => $q,
            'filter_role'  => $role,
        ]);
    }

    /**
     * Build filtered user query based on current user's scope.
     */
    protected function buildFilteredQuery(string $status, string $q, string $role)
    {
        $sessionUser = session('user');
        $assignment = $sessionUser['assignment'] ?? '';

        $query = CallCenterUser::query()
            ->with('supervisorUser')
            ->withCount(['supervisedUsers', 'interactionsAsAgent', 'rowAssignments']);

        // Scope users based on current user's role
        if ($assignment === 'super') {
            // Super admin sees all CC users
        } elseif ($assignment && ! str_starts_with($assignment, 'rtom_')
            && ! str_starts_with($assignment, 'supervisor_')
            && ! str_starts_with($assignment, 'caller_')
        ) {
            // Region admin: see users in their region
            $region = $assignment;
            $query->where(function ($q) use ($region) {
                // Region admins (assignment = region name)
                $q->where('assignment', $region)
                  // RTOM admins they supervise
                  ->orWhere(function ($sq) use ($region) {
                      $sq->where('assignment', 'like', 'rtom_%')
                         ->where('supervisor', session('user')['id'] ?? null);
                  })
                  // Supervisors they created
                  ->orWhere(function ($sq) use ($region) {
                      $sq->where('assignment', 'like', 'supervisor_rtom_%')
                         ->where('supervisor', session('user')['id'] ?? null);
                  })
                  // Callers under their supervisors
                  ->orWhere(function ($sq) {
                      $sq->where('assignment', 'like', 'caller_%')
                         ->whereIn('supervisor', function ($sub) {
                             $sub->select('id')
                                 ->from('users')
                                 ->where('supervisor', session('user')['id'] ?? null)
                                 ->where('system', 'cc');
                         });
                  });
            });
        } elseif (str_starts_with($assignment, 'rtom_')) {
            // RTOM admin: see supervisors and callers under their RTOM
            $rtom = $this->currentRtom();
            $query->where(function ($q) use ($rtom) {
                $q->where('assignment', 'supervisor_rtom_' . strtolower(str_replace(' ', '_', $rtom)))
                  ->orWhere('assignment', 'caller_rtom_' . strtolower(str_replace(' ', '_', $rtom)));
            });
        } elseif (str_starts_with($assignment, 'supervisor_')) {
            // Supervisor: see only callers under them
            $rtom = $this->currentRtom();
            $query->where('assignment', 'caller_rtom_' . strtolower(str_replace(' ', '_', $rtom)))
                  ->where('supervisor', $sessionUser['id'] ?? null);
        }

        // Status filter
        if ($status === 'active') {
            $query->where('status', true);
        } elseif ($status === 'disabled') {
            $query->where('status', false);
        }

        // Role filter
        if ($role === 'super_admin') {
            $query->where('assignment', 'super');
        } elseif ($role === 'region_admin') {
            $query->where('assignment', 'like', 'REGION%');
        } elseif ($role === 'rtom_admin') {
            $query->where('assignment', 'like', 'rtom_%');
        } elseif ($role === 'supervisor') {
            $query->where('assignment', 'like', 'supervisor_%');
        } elseif ($role === 'caller') {
            $query->where('assignment', 'like', 'caller_%');
        }

        // Search filter
        if ($q !== '') {
            $query->where(function ($wq) use ($q) {
                $wq->where('username', 'like', '%' . $q . '%')
                   ->orWhere('name', 'like', '%' . $q . '%');
            });
        }

        return $query;
    }

    /**
     * Show the role assignment form for a specific user.
     */
    public function assignForm(User $user): View
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        $allowedRoles = $this->getAllowedRoles();
        if (empty($allowedRoles)) {
            abort(403, 'You do not have permission to assign roles.');
        }

        // Cannot change own role
        if ((int) $user->id === (int) ($sessionUser['id'] ?? 0)) {
            abort(403, 'You cannot change your own role.');
        }

        // Ensure user is a CC user
        if ($user->system !== 'cc') {
            abort(404, 'User is not a Call Center user.');
        }

        $regions = $this->availableRegions();
        $rtoms = $this->availableRtoms();
        $supervisors = $this->availableSupervisors();
        $rtomAdmins = $this->availableRtomAdmins();

        return view('cc.management.assign', compact(
            'user',
            'allowedRoles',
            'regions',
            'rtoms',
            'supervisors',
            'rtomAdmins'
        ));
    }

    /**
     * Process the role assignment.
     */
    public function assignStore(Request $request, User $user): RedirectResponse
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        $allowedRoles = $this->getAllowedRoles();
        if (empty($allowedRoles)) {
            abort(403, 'You do not have permission to assign roles.');
        }

        // Cannot change own role
        if ((int) $user->id === (int) ($sessionUser['id'] ?? 0)) {
            abort(403, 'You cannot change your own role.');
        }

        if ($user->system !== 'cc') {
            abort(404, 'User is not a Call Center user.');
        }

        $roleKeys = array_keys($allowedRoles);

        $rules = [
            'role' => 'required|in:' . implode(',', $roleKeys),
            'region' => 'required_if:role,region|nullable|string|max:255',
            'rtom' => 'required_if:role,rtom_admin,supervisor,caller|nullable|string|max:255',
            'supervisor_id' => 'required_if:role,caller|nullable|integer|exists:users,id',
            'rtom_admin_id' => 'required_if:role,supervisor|nullable|integer|exists:users,id',
        ];

        $validated = $request->validate($rules);

        // Build assignment string and update fields
        $role = $validated['role'];

        switch ($role) {
            case 'super':
                $user->assignment = 'super';
                $user->admin_prev = true;
                $user->supervisor = $sessionUser['id'] ?? null;
                break;

            case 'region':
                $region = $validated['region'];
                $allowedRegions = $this->availableRegions();
                if (! in_array($region, $allowedRegions, true)) {
                    return back()->withErrors(['region' => 'Selected region is not available.']);
                }
                $user->assignment = $region;
                $user->admin_prev = true;
                $user->supervisor = $sessionUser['id'] ?? null;
                break;

            case 'rtom_admin':
                $rtom = $validated['rtom'];
                $allowedRtoms = $this->availableRtoms();
                if (! in_array($rtom, $allowedRtoms, true)) {
                    return back()->withErrors(['rtom' => 'Selected RTOM is not available.']);
                }
                $user->assignment = 'rtom_' . preg_replace('/\s+/', '_', strtolower($rtom));
                $user->admin_prev = true;
                $user->supervisor = $sessionUser['id'] ?? null;
                break;

            case 'supervisor':
                $rtom = $validated['rtom'];
                $allowedRtoms = $this->availableRtoms();
                if (! in_array($rtom, $allowedRtoms, true)) {
                    return back()->withErrors(['rtom' => 'Selected RTOM is not available.']);
                }
                $user->assignment = 'supervisor_rtom_' . preg_replace('/\s+/', '_', strtolower($rtom));
                $user->admin_prev = false;
                // Supervisor is assigned to the RTOM admin or region admin who creates them
                $user->supervisor = $validated['rtom_admin_id'] ?? ($sessionUser['id'] ?? null);
                break;

            case 'caller':
                $rtom = $validated['rtom'];
                $allowedRtoms = $this->availableRtoms();
                if (! in_array($rtom, $allowedRtoms, true)) {
                    return back()->withErrors(['rtom' => 'Selected RTOM is not available.']);
                }
                $user->assignment = 'caller_rtom_' . preg_replace('/\s+/', '_', strtolower($rtom));
                $user->admin_prev = false;
                $user->supervisor = $validated['supervisor_id'] ?? ($sessionUser['id'] ?? null);
                break;
        }

        $user->status = $user->status ?? true; // Ensure active
        $user->save();

        return redirect()->route('cc.management.index')
            ->with('status', "Role updated: {$user->username} is now " . ($allowedRoles[$role] ?? $role) . '.');
    }

    /**
     * Toggle user status (enable/disable).
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        if (empty($this->getAllowedRoles())) {
            abort(403);
        }

        if ((int) $user->id === (int) ($sessionUser['id'] ?? 0)) {
            abort(403, 'You cannot disable your own account.');
        }

        $user->status = ! (bool) $user->status;
        $user->save();

        $action = $user->status ? 'enabled' : 'disabled';
        return redirect()->route('cc.management.index')
            ->with('status', "User {$user->username} has been {$action}.");
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        if (empty($this->getAllowedRoles())) {
            abort(403);
        }

        if ((int) $user->id === (int) ($sessionUser['id'] ?? 0)) {
            abort(403, 'You cannot delete your own account.');
        }

        if ($user->fixed) {
            return back()->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        if ($user->supervisedUsers()->exists()) {
            return back()->withErrors(['delete' => 'This user has supervised employees and cannot be deleted.']);
        }

        if ($user->interactionsAsAgent()->exists()) {
            return back()->withErrors(['delete' => 'This user has call interactions and cannot be deleted.']);
        }

        if ($user->rowAssignments()->exists()) {
            return back()->withErrors(['delete' => 'This user has active assignments and cannot be deleted.']);
        }

        $username = $user->username;
        $user->delete();

        return redirect()->route('cc.management.index')
            ->with('status', "User {$username} has been deleted.");
    }
}
