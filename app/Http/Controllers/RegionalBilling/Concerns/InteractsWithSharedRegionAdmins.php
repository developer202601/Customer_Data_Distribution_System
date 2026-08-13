<?php

namespace App\Http\Controllers\RegionalBilling\Concerns;

use App\Models\User;

trait InteractsWithSharedRegionAdmins
{
    /**
     * Get all region admin IDs that share the same region as the current user.
     *
     * This mirrors the CC segment admin sharing model but for RB region admins.
     * Region admins are grouped by their assignment value (the region name).
     * All active region admins in the same region form a shared RTOM-management group.
     *
     * @return array<int>
     */
    protected function getSharedRegionAdminIds(): array
    {
        $sessionUser = session('user');
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        if ($currentUserId <= 0) {
            return [];
        }

        $assignment = $sessionUser['assignment'] ?? '';
        if ($assignment === 'super' || str_starts_with($assignment, ['rtom_', 'caller_', 'supervisor_'])) {
            return [$currentUserId];
        }

        return User::where('system', 'rb')
            ->where('admin_prev', 1)
            ->where('assignment', $assignment)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
