<?php

namespace App\Jobs;

use App\Models\CallCenterAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReassignCallCenterRows implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $assignmentIds;
    public array $userIds;

    public function __construct(array $assignmentIds, array $userIds)
    {
        $this->assignmentIds = $assignmentIds;
        $this->userIds = array_values(array_filter($userIds, fn ($id) => is_numeric($id) && $id > 0));
    }

    public function handle(): void
    {
        if (empty($this->assignmentIds)) {
            return;
        }

        $assignments = CallCenterAssignment::whereIn('id', $this->assignmentIds)
            ->orderBy('id')
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        if (empty($this->userIds)) {
            // Reset to pool so admin can pick later
            CallCenterAssignment::whereIn('id', $this->assignmentIds)
                ->update([
                    'assigned_user_id' => null,
                    'status' => 'pending',
                    'accepted' => false,
                    'accepted_at' => null,
                    'rejected' => false,
                    'rejected_at' => null,
                    'rejected_by' => null,
                    'rejection_note' => null,
                    'locked_at' => null,
                    'locked_by' => null,
                ]);

            return;
        }

        $users = $this->userIds;
        $count = count($users);

        foreach ($assignments as $index => $assignment) {
            $assignment->update([
                'assigned_user_id' => $users[$index % $count],
                'status' => 'pending',
                'accepted' => false,
                'accepted_at' => null,
                'rejected' => false,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_note' => null,
                'locked_at' => null,
                'locked_by' => null,
            ]);
        }
    }
}
