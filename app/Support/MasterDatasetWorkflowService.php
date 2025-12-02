<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use App\Support\MasterDatasetProcessStatus;

class MasterDatasetWorkflowService
{
    public function __construct(
        private MasterDatasetImporter $importer,
        private MasterDatasetExclusionService $exclusionService,
        private MasterDatasetAssignmentService $assignmentService,
    ) {
    }

    /**
     * Persist the master archive while deferring validation until exclusions are
     * available.
     */
    public function queueMasterArchive(UploadedFile $masterArchive, ?array $userContext = null): MasterDatasetProcess
    {
        return $this->importer->queue($masterArchive, $userContext);
    }

    /**
     * Once exclusions are provided, validate/ingest the master workbook and then
     * apply the uploaded exclusion archives.
     */
    public function finalizeWithExclusions(
        MasterDatasetProcess $process,
        array $exclusionArchives,
        ?array $userContext = null
    ): array {
        $filteredExclusions = array_values(array_filter(
            $exclusionArchives,
            static fn ($file) => $file instanceof UploadedFile
        ));

        if (empty($filteredExclusions)) {
            throw ValidationException::withMessages([
                'exclusions' => 'Please upload at least one exclusion ZIP archive.',
            ]);
        }

        $processed = $this->importer->processStoredArchive($process, $userContext)->fresh();

        $processed = MasterDatasetProcessStatus::set($processed, MasterDatasetProcessStatus::EXCLUSIONS_APPLYING);
        sleep(1); // Allow polling to capture this status
        
        $exclusionResult = $this->exclusionService->apply($processed, $filteredExclusions);
        $processed = MasterDatasetProcessStatus::set($processed->fresh(), MasterDatasetProcessStatus::EXCLUSIONS_APPLIED);
        sleep(1); // Allow polling to capture this status

        $processed = MasterDatasetProcessStatus::set($processed, MasterDatasetProcessStatus::VIP_CHECKING);
        sleep(1); // Allow polling to capture this status
        
        $assignmentResult = $this->assignmentService->assign($processed->fresh());
        $processed = MasterDatasetProcessStatus::set($processed->fresh(), MasterDatasetProcessStatus::VIP_READY);
        sleep(1); // Allow polling to capture this status
        
        $processed = MasterDatasetProcessStatus::set($processed, MasterDatasetProcessStatus::RETAIL_MICRO_CHECKING);
        sleep(1); // Allow polling to capture this status
        
        $processed = MasterDatasetProcessStatus::set($processed, MasterDatasetProcessStatus::RETAIL_MICRO_READY);
        sleep(1); // Allow polling to capture this status

        return [
            'process' => $processed->fresh(),
            'exclusions' => $exclusionResult,
            'assignments' => $assignmentResult,
        ];
    }
}
