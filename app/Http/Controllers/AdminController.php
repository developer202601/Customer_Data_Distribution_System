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
    

    public function config()
    {
        $configs = Configurations::with('editor')
            ->whereIn('config_name', ['upper_range', 'lower_range', 'ccs', 'cc', 's'])
            ->get()
            ->keyBy('config_name');

        $billRangeUpdated = $this->latestMeta($configs, ['upper_range', 'lower_range']);
        $staffUpdated = $this->latestMeta($configs, ['ccs', 'cc', 's']);

        // Fetch master system users (excluding call center users)
        $users = User::where(function ($q) {
                $q->where('system', '!=', 'cc')
                    ->orWhereNull('system');
            })
            ->where('admin_prev', false)
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

        $user = User::create([
            'username' => $validated['username'],
            'name' => null,
            'admin_prev' => false,
            // Match existing master system rows.
            'system' => 'master',
            'fixed' => false,
            'status' => true,
            'created_at' => now(),
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
