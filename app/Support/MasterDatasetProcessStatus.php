<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;

class MasterDatasetProcessStatus
{
    public const AWAITING_EXCLUSIONS = 'awaiting_exclusions';
    public const VALIDATING = 'validating';
    public const VALIDATED = 'validated';
    public const PYTHON_RUNNING = 'python_running';
    public const PYTHON_COMPLETE = 'python_complete';
    public const RECORDS_INSERTING = 'records_inserting';
    public const RECORDS_INSERTED = 'records_inserted';
    public const EXCLUSIONS_APPLYING = 'exclusions_applying';
    public const EXCLUSIONS_APPLIED = 'exclusions_applied';
    public const VIP_CHECKING = 'vip_checking';
    public const VIP_READY = 'vip_ready';
    public const RETAIL_MICRO_CHECKING = 'retail_micro_checking';
    public const RETAIL_MICRO_READY = 'retail_micro_ready';
    public const EXPORTS_PENDING = 'exports_pending';
    public const READY = 'ready';
    public const FAILED = 'failed';

    /**
     * Status values in the order they occur during processing.
     */
    private const STATUS_SEQUENCE = [
        self::AWAITING_EXCLUSIONS,
        self::VALIDATING,
        self::VALIDATED,
        self::PYTHON_RUNNING,
        self::PYTHON_COMPLETE,
        self::RECORDS_INSERTING,
        self::RECORDS_INSERTED,
        self::EXCLUSIONS_APPLYING,
        self::EXCLUSIONS_APPLIED,
        self::VIP_CHECKING,
        self::VIP_READY,
        self::RETAIL_MICRO_CHECKING,
        self::RETAIL_MICRO_READY,
        self::EXPORTS_PENDING,
        self::READY,
        self::FAILED,
    ];

    private const STAGE_DEFINITIONS = [
        [
            'key' => 'validation',
            'active_status' => self::VALIDATING,
            'complete_status' => self::VALIDATED,
            'active_label' => 'Validating dataset…',
            'complete_label' => 'Validated dataset.',
        ],
        [
            'key' => 'python',
            'active_status' => self::PYTHON_RUNNING,
            'complete_status' => self::PYTHON_COMPLETE,
            'active_label' => 'Running Python ingestion…',
            'complete_label' => 'Python ingestion complete.',
        ],
        [
            'key' => 'records',
            'active_status' => self::RECORDS_INSERTING,
            'complete_status' => self::RECORDS_INSERTED,
            'active_label' => 'Inserting records…',
            'complete_label' => 'Records inserted.',
        ],
        [
            'key' => 'exclusions',
            'active_status' => self::EXCLUSIONS_APPLYING,
            'complete_status' => self::EXCLUSIONS_APPLIED,
            'active_label' => 'Applying exclusions…',
            'complete_label' => 'Exclusions applied.',
        ],
        [
            'key' => 'vip',
            'active_status' => self::VIP_CHECKING,
            'complete_status' => self::VIP_READY,
            'active_label' => 'Verifying VIP segments…',
            'complete_label' => 'VIP segments verified.',
        ],
        [
            'key' => 'retail_micro',
            'active_status' => self::RETAIL_MICRO_CHECKING,
            'complete_status' => self::RETAIL_MICRO_READY,
            'active_label' => 'Reviewing Retail & Micro segments…',
            'complete_label' => 'Retail & Micro segments ready.',
        ],
        [
            'key' => 'exports',
            'active_status' => self::EXPORTS_PENDING,
            'complete_status' => self::READY,
            'active_label' => 'Generating exports…',
            'complete_label' => 'Exports ready.',
        ],
    ];

    /**
     * Update the process status, returning the refreshed model.
     * Uses a separate database connection to bypass any active transactions so status
     * updates are immediately visible to polling requests.
     */
    public static function set(MasterDatasetProcess $process, string $status): MasterDatasetProcess
    {
        if ($process->status === $status) {
            return $process;
        }

        \Log::info("STATUS UPDATE: {$process->id} => {$status}");

        // Update via Eloquent on the default connection to ensure proper transaction handling
        $process->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        // Force refresh to get the latest committed value
        $process->refresh();

        return $process;
    }

    /**
     * Convert the current status into stage states for the UI.
     */
    public static function summarize(?string $status): array
    {
        $status = $status ?: self::AWAITING_EXCLUSIONS;
        $sequence = self::STATUS_SEQUENCE;
        $sequenceLookup = array_flip($sequence);
        $currentIndex = $sequenceLookup[$status] ?? -1;

        $stages = [];
        $completed = 0;
        $activeKey = null;

        foreach (self::STAGE_DEFINITIONS as $definition) {
            $activeIndex = $sequenceLookup[$definition['active_status']] ?? null;
            $completeIndex = $sequenceLookup[$definition['complete_status']] ?? null;

            $state = 'pending';

            if ($status === self::FAILED) {
                if ($completeIndex !== null && $currentIndex >= $completeIndex) {
                    $state = 'complete';
                } elseif ($activeIndex !== null && $currentIndex >= $activeIndex) {
                    $state = 'failed';
                }
            } else {
                if ($completeIndex !== null && $currentIndex >= $completeIndex) {
                    $state = 'complete';
                } elseif ($activeIndex !== null && $currentIndex === $activeIndex) {
                    $state = 'active';
                } elseif ($activeIndex !== null && $currentIndex > $activeIndex) {
                    // Move to the next status without an explicit complete state.
                    $state = $completeIndex !== null && $currentIndex >= $completeIndex ? 'complete' : 'pending';
                }
            }

            if ($state === 'active') {
                $activeKey = $definition['key'];
            }

            if ($state === 'complete') {
                $completed++;
            }

            $progress = match ($state) {
                'complete' => 100,
                'active' => 50,
                'failed' => 100,
                default => 0,
            };

            $label = $state === 'active'
                ? $definition['active_label']
                : $definition['complete_label'];

            $stages[] = [
                'key' => $definition['key'],
                'state' => $state,
                'progress' => $progress,
                'message' => $label,
            ];
        }

        $totalStages = count(self::STAGE_DEFINITIONS);
        $overall = 0;
        if ($totalStages > 0) {
            $stageWeight = 100 / $totalStages;
            $overall = (int) round(($completed * $stageWeight) + ($activeKey !== null ? $stageWeight / 2 : 0));

            if ($activeKey !== null && $overall >= 100) {
                $overall = 99;
            }
        }

        $message = self::messageFor($status, $stages, $activeKey);

        return [
            'status' => $status,
            'stages' => $stages,
            'active_stage' => $activeKey,
            'overall_progress' => $overall,
            'message' => $message,
        ];
    }

    public static function summarizeProcess(MasterDatasetProcess $process): array
    {
        return self::summarize($process->status);
    }

    private const STATUS_MESSAGES = [
        self::AWAITING_EXCLUSIONS => 'Waiting for exclusions…',
        self::VALIDATING => 'Validating dataset…',
        self::VALIDATED => 'Dataset validated.',
        self::PYTHON_RUNNING => 'Running Python ingestion…',
        self::PYTHON_COMPLETE => 'Python ingestion complete.',
        self::RECORDS_INSERTING => 'Inserting records…',
        self::RECORDS_INSERTED => 'Records inserted.',
        self::EXCLUSIONS_APPLYING => 'Applying exclusions…',
        self::EXCLUSIONS_APPLIED => 'Exclusions applied.',
        self::VIP_CHECKING => 'Verifying VIP segments…',
        self::VIP_READY => 'VIP segments verified.',
        self::RETAIL_MICRO_CHECKING => 'Reviewing Retail & Micro segments…',
        self::RETAIL_MICRO_READY => 'Retail & Micro segments ready.',
        self::EXPORTS_PENDING => 'Generating exports…',
        self::READY => 'Exports ready.',
        self::FAILED => 'Processing failed.',
    ];

    private static function messageFor(string $status, array $stages, ?string $activeKey): string
    {
        return self::STATUS_MESSAGES[$status] ?? 'Processing…';
    }

    public static function statusSequence(): array
    {
        return self::STATUS_SEQUENCE;
    }

    /**
     * Get the progress percentage for a given status.
     * Groups statuses into logical stages to provide consistent progression.
     */
    public static function getProgressPercentage(string $status): int
    {
        // Even distribution across the ordered sequence for smoother 0..100 progression.
        $sequence = self::STATUS_SEQUENCE;
        $count = count($sequence);
        $index = array_search($status, $sequence, true);
        if ($index === false) {
            return 0;
        }
        if ($status === self::FAILED) {
            return 0;
        }
        // First status (awaiting_exclusions) -> 0%, last (ready) -> 100%.
        if ($count <= 1) {
            return 100;
        }
        $percentage = (int) round(($index / ($count - 1)) * 100);
        return max(0, min(100, $percentage));
    }

    /**
     * Get friendly display name for a status.
     */
    public static function getFriendlyName(string $status): string
    {
        return self::STATUS_MESSAGES[$status] ?? $status;
    }
}
