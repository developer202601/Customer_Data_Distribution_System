<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\Configurations;
use App\Models\User;

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
        $users = User::where('system', '!=', 'cc')
            ->orWhereNull('system')
            ->orderBy('username')
            ->get();

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
        // Instead of deleting, set status to false
        $user->update(['status' => false]);

        return response()->json(['success' => true]);
    }
}
