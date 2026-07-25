<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    /** The three fixed CC segments */
    private const SEGMENTS = [
        'segment_ccs' => 'Call Center Staff',
        'segment_cc'  => 'Call Center',
        'segment_s'   => 'Staff',
    ];

    private function ensureSuper(): void
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }
    }

    // -------------------------------------------------------------------------
    // Segment admin creation
    // -------------------------------------------------------------------------

    public function createUserForm()
    {
        $this->ensureSuper();

        return view('cc.super.create', [
            'segments' => self::SEGMENTS,
        ]);
    }

    public function storeUser(Request $request)
    {
        $this->ensureSuper();

        $sessionUser = session('user');

        $request->validate([
            'username' => 'required|string|size:6|unique:users,username',
            'name'     => 'nullable|string|max:255',
            'segment'  => 'required|in:segment_ccs,segment_cc,segment_s',
        ]);

        $user = new User();
        $user->username   = $request->input('username');
        $user->name       = $request->input('name');
        $user->system     = 'cc';
        $user->admin_prev = 1;
        $user->assignment = $request->input('segment');
        $user->status     = 1;
        $user->supervisor = $sessionUser['id'] ?? null;
        $user->created_at = now();
        $user->save();

        $label = self::SEGMENTS[$request->input('segment')] ?? $request->input('segment');

        return redirect()->route('cc.super.segments')
            ->with('status', "Segment admin created for {$label}.");
    }

    // -------------------------------------------------------------------------
    // Segment admin list / search / edit
    // -------------------------------------------------------------------------

    public function indexSegments()
    {
        $this->ensureSuper();

        $sessionUser = session('user');
        $q               = request()->query('q');
        $selectedSegment = request()->query('segment');

        $query = User::where('system', 'cc')
            ->where('assignment', 'like', 'segment_%')
            ->where('supervisor', $sessionUser['id'] ?? null);

        if (! empty($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedSegment)) {
            $query->where('assignment', $selectedSegment);
        }

        $segmentAdmins = $query->orderBy('assignment')->orderBy('username')->get();

        return view('cc.super.segments', compact('segmentAdmins', 'q', 'selectedSegment'));
    }

    public function searchSegments(Request $request)
    {
        $this->ensureSuper();

        $sessionUser = session('user');
        $q               = $request->query('q');
        $selectedSegment = $request->query('segment');

        $query = User::where('system', 'cc')
            ->where('assignment', 'like', 'segment_%')
            ->where('supervisor', $sessionUser['id'] ?? null);

        if (! empty($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedSegment)) {
            $query->where('assignment', $selectedSegment);
        }

        $segmentAdmins = $query->orderBy('assignment')->orderBy('username')->get();

        return view('cc.super._segment_rows', compact('segmentAdmins'));
    }

    public function editSegmentAdminForm(User $user)
    {
        $this->ensureSuper();

        if ($user->system !== 'cc' || ! str_starts_with((string) ($user->assignment ?? ''), 'segment_')) {
            abort(404);
        }

        return view('cc.super.edit_segment', [
            'user'     => $user,
            'segments' => self::SEGMENTS,
        ]);
    }

    public function updateSegmentAdmin(Request $request, User $user)
    {
        $this->ensureSuper();

        if ($user->system !== 'cc' || ! str_starts_with((string) ($user->assignment ?? ''), 'segment_')) {
            abort(404);
        }

        $request->validate([
            'name'    => 'nullable|string|max:45',
            'segment' => 'required|in:segment_ccs,segment_cc,segment_s',
        ]);

        $user->name = $request->input('name');
        if (! $user->fixed) {
            $user->assignment = $request->input('segment');
        }
        $user->save();

        return redirect()->route('cc.super.segments')
            ->with('status', 'Segment admin updated successfully.');
    }

    // -------------------------------------------------------------------------
    // Legacy assign form (promote existing user to segment admin or super)
    // -------------------------------------------------------------------------

    public function showAssignForm(User $user)
    {
        $this->ensureSuper();

        return view('cc.super.assign', [
            'user'     => $user,
            'segments' => self::SEGMENTS,
        ]);
    }

    public function indexAssign()
    {
        $this->ensureSuper();

        $sessionUser = session('user');

        $users = User::where('system', 'cc')
            ->where(function ($q) {
                $q->where('assignment', 'like', 'segment_%')
                  ->orWhere(function ($sq) {
                      $sq->where('assignment', 'super')
                         ->where('admin_prev', 1);
                  });
            })
            ->where('id', '!=', $sessionUser['id'])
            ->orderBy('id')
            ->get();

        return view('cc.super.index', compact('users'));
    }

    public function storeAssignment(Request $request, User $user)
    {
        $this->ensureSuper();

        $request->validate([
            'role'    => 'required|in:super,segment',
            'segment' => 'required_if:role,segment|nullable|in:segment_ccs,segment_cc,segment_s',
        ]);

        if ($request->input('role') === 'segment') {
            $user->admin_prev = 1;
            $user->assignment = $request->input('segment');
        } else {
            $user->admin_prev = 1;
            $user->assignment = 'super';
        }

        $user->save();

        return redirect()->route('cc.users.assign.index')
            ->with('status', 'Assignment updated.');
    }

    /** Expose segment labels for use in views */
    public static function segmentLabels(): array
    {
        return self::SEGMENTS;
    }

    // -------------------------------------------------------------------------
    // RB region admin creation (CC super admin can also create RB region admins)
    // -------------------------------------------------------------------------

    public function createRbRegionForm()
    {
        $this->ensureSuper();

        $lastTwoProcessIds = \App\Models\MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $regions = collect();
        if (! empty($lastTwoProcessIds)) {
            $regions = \App\Models\MasterDatasetRow::whereIn('process_id', $lastTwoProcessIds)
                ->whereNotNull('region')
                ->pluck('region')
                ->unique()
                ->values();
        }

        return view('cc.super.create_rb_region', compact('regions'));
    }

    public function storeRbRegion(Request $request)
    {
        $this->ensureSuper();

        $sessionUser = session('user');

        $request->validate([
            'username'      => 'required|string|size:6|unique:users,username',
            'name'          => 'nullable|string|max:255',
            'region'        => 'required|string|max:45',
            'region_source' => 'required|in:list,custom',
        ]);

        $region       = trim($request->input('region'));
        $regionSource = $request->input('region_source');

        if ($regionSource === 'list') {
            $lastTwoProcessIds = \App\Models\MasterDatasetRow::select('process_id')
                ->distinct()
                ->orderBy('process_id', 'desc')
                ->limit(2)
                ->pluck('process_id')
                ->toArray();

            $allowedRegions = [];
            if (! empty($lastTwoProcessIds)) {
                $allowedRegions = \App\Models\MasterDatasetRow::whereIn('process_id', $lastTwoProcessIds)
                    ->whereNotNull('region')
                    ->pluck('region')
                    ->unique()
                    ->values()
                    ->toArray();
            }

            if (! in_array($region, $allowedRegions, true)) {
                return back()->withErrors(['region' => 'Selected region is not available from the last two reports.']);
            }
        }
        // custom: any non-empty string accepted

        $user = new User();
        $user->username   = $request->input('username');
        $user->name       = $request->input('name');
        $user->system     = 'rb';
        $user->admin_prev = 1;
        $user->assignment = $region;
        $user->status     = 1;
        $user->supervisor = $sessionUser['id'] ?? null;
        $user->created_at = now();
        $user->save();

        return redirect()->route('cc.super.segments')
            ->with('status', "RB region admin created for {$region}.");
    }
}
