<?php

namespace App\Http\Controllers\CallCenter\Concerns;

use App\Models\User;

trait InteractsWithSharedSegmentAdmins
{
    /**
     * Get all segment admin IDs that share the same segment as the current user.
     *
     * This mirrors the RB RTOM sharing model (getSharedRtomAdminUserIds) but
     * simplified: CC segment admins are grouped by their assignment value
     * (segment_ccs, segment_cc, segment_s). All active segment admins in the
     * same segment form a shared caller-management group.
     *
     * @return array<int>
     */
    protected function getSharedSegmentAdminIds(): array
    {
        $sessionUser = session('user');
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        if ($currentUserId <= 0) {
            return [];
        }

        $assignment = $sessionUser['assignment'] ?? '';
        if (!str_starts_with($assignment, 'segment_')) {
            return [$currentUserId];
        }

        return User::where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('assignment', $assignment)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
