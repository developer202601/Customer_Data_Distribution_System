<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Configurations;
use App\Models\DatasetExport;
use App\Models\MasterDatasetProcess;
use App\Models\User;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    

    public function config(Request $request)
    {
        $isAdmin = (bool) ($request->session()->get('user.is_admin') ?? false);
        $isCc = (string) ($request->session()->get('user.system') ?? '') === 'cc';
        if (! $isAdmin && ! $isCc) {
            return redirect()->route('dashboard')->withErrors([
                'auth' => 'Only administrators can access configurations.',
            ]);
        }

        $configs = Configurations::with('editor')
            ->whereIn('config_name', ['upper_range', 'lower_range', 'ccs', 'cc', 's', 'medium_ftth', 'medium_copper', 'medium_lte', 'outstanding_threshold'])
            ->get()
            ->keyBy('config_name');

        $billRangeUpdated = $this->latestMeta($configs, ['upper_range', 'lower_range']);
        $staffUpdated = $this->latestMeta($configs, ['ccs', 'cc', 's']);
        $mediumsUpdated = $this->latestMeta($configs, ['medium_ftth', 'medium_copper', 'medium_lte']);
        $outstandingThresholdUpdated = $this->latestMeta($configs, ['outstanding_threshold']);

        $adminId = auth()->id() ?: $request->session()->get('user.id');

        // Fetch master system users created by this admin account
        $users = User::where(function ($q) {
                 $q->where('system', 'master')
                    ->orWhereNull('system');
            })
            ->where('admin_prev', false)
            ->where('supervisor', $adminId)
            ->orderBy('username')
            ->get();

        $userIds = $users->pluck('id')->filter()->values();
        $processUserIds = $userIds->isEmpty()
            ? collect()
            : MasterDatasetProcess::query()
                ->whereIn('user_id', $userIds)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

        $exportUserIds = $userIds->isEmpty()
            ? collect()
            : DatasetExport::query()
                ->whereIn('user_id', $userIds)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

        $usersWithReports = $processUserIds
            ->merge($exportUserIds)
            ->unique()
            ->flip();

        $users->each(function (User $u) use ($usersWithReports) {
            $u->setAttribute('has_generated_reports', $usersWithReports->has($u->id));
        });

        return view('admin/adminconfig', [
            'configs' => $configs,
            'billRangeUpdated' => $billRangeUpdated,
            'staffUpdated' => $staffUpdated,
            'mediumsUpdated' => $mediumsUpdated,
            'outstandingThresholdUpdated' => $outstandingThresholdUpdated,
            'users' => $users,
        ]);
    }

    private function latestMeta($configs, array $keys): array
    {
        $subset = collect($configs)->only($keys)->filter();
        if ($subset->isEmpty()) {
            return ['timestamp' => null, 'editor' => null];
        }

        $latest = $subset->sortByDesc('updated_at')->first();

        return [
            'timestamp' => $latest?->updated_at,
            'editor' => $latest?->editor,
        ];
    }

    public function updateUserStatus(User $user, Request $request)
    {
        $request->validate([
            'status' => 'required|boolean',
        ]);

        $user->update(['status' => $request->status]);

        return response()->json(['success' => true]);
    }

    public function updateUserName(User $user, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user->update(['name' => $request->name]);

        return response()->json(['success' => true]);
    }

    public function deleteUser(User $user)
    {
        $hasGeneratedReports = MasterDatasetProcess::query()
            ->where('user_id', $user->id)
            ->exists()
            || DatasetExport::query()
                ->where('user_id', $user->id)
                ->exists();

        if ($hasGeneratedReports) {
            return response()->json([
                'success' => false,
                'message' => 'This user has generated reports and cannot be deleted.',
            ], 422);
        }

        try {
            $user->delete();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user.',
            ], 500);
        }

        return response()->json(['success' => true]);
    }

    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                Rule::unique('users', 'username'),
            ],
        ]);

        $adminId = auth()->id() ?: $request->session()->get('user.id');

        $user = User::create([
            'username' => $validated['username'],
            'name' => null,
            'admin_prev' => false,
            // Match existing master system rows.
            'system' => 'master',
            'fixed' => false,
            'status' => true,
            'created_at' => now(),
            'supervisor' => $adminId,
        ]);

        $user->setAttribute('has_generated_reports', false);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'status' => (bool) $user->status,
                'has_generated_reports' => false,
            ],
        ]);
    }
}
