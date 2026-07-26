<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CallCenter\DashboardController as CallCenterDashboardController;
use App\Http\Controllers\CallCenter\ReportController as CallCenterReportController;
use App\Http\Controllers\CallCenter\UserController as CallCenterUserController;
use App\Http\Controllers\DatasetReportsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExclusionUploadController;
use App\Http\Controllers\MasterDatasetUploadController;
use App\Http\Controllers\MasterValidationReportController;
use App\Http\Controllers\PaymentUploadController;
use App\Http\Controllers\ProcessFileController;
use App\Http\Controllers\ProcessRunningController;
use App\Http\Controllers\ProcessStatusController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BillRangeController;

Route::middleware('session.auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/master/upload', [MasterDatasetUploadController::class, 'create'])->name('master.upload.create');
    Route::get('/master/upload/config', [MasterDatasetUploadController::class, 'assignmentConfig'])->name('master.upload.assignment.config');
    Route::post('/master/upload/chunks/start', [MasterDatasetUploadController::class, 'startChunkUpload'])->name('master.upload.chunks.start');
    Route::post('/master/upload/chunks/part', [MasterDatasetUploadController::class, 'uploadChunk'])->name('master.upload.chunks.part');
    Route::post('/master/upload/chunks/finish', [MasterDatasetUploadController::class, 'finishChunkUpload'])->name('master.upload.chunks.finish');
    Route::post('/master/upload/chunks/submit', [MasterDatasetUploadController::class, 'submitChunkUpload'])->name('master.upload.chunks.submit');
    Route::delete('/master/upload/chunks/upload/{token}', [MasterDatasetUploadController::class, 'cancelChunkUpload'])->name('master.upload.chunks.cancel');
    Route::delete('/master/upload/chunks/staged/{token}', [MasterDatasetUploadController::class, 'destroyStagedUpload'])->name('master.upload.chunks.staged.destroy');
    Route::post('/master/upload', [MasterDatasetUploadController::class, 'store'])->name('master.upload.store');
    Route::get('/master/upload/validation-report/{token}', [MasterValidationReportController::class, 'download'])->name('master.validation.report.download');
    Route::get('/process/upload', [ProcessFileController::class, 'create'])->name('process.upload.create');
    Route::get('/payment/upload', function (\Illuminate\Http\Request $request) {
        if ($request->session()->get('user.is_admin')) {
            return redirect()->route('process.assignments.reports')->with('status', 'File uploads are reserved for normal users.');
        }
        return view('process.payment-upload');
    })->name('payment.upload');
    Route::post('/payments/update', [PaymentUploadController::class, 'update'])->name('payments.update');
    Route::get('/payments', [PaymentUploadController::class, 'index'])->name('payments.index');
    Route::get('/payments/progress/{token}', [PaymentUploadController::class, 'progress'])->name('payments.progress');
    Route::get('/payments/progress/stream/{token}', [PaymentUploadController::class, 'progressStream'])->name('payments.progress.stream');
    Route::post('/process/upload', [ProcessFileController::class, 'store'])->name('process.upload.store');
    Route::post('/process/upload/cancel', [ProcessFileController::class, 'cancel'])->name('process.upload.cancel');
    Route::get('/process/upload/progress/{token}', [ProcessFileController::class, 'progress'])->name('process.upload.progress');
    Route::get('/process/upload/progress/stream/{token}', [ProcessFileController::class, 'progressStream'])->name('process.upload.progress.stream');
    Route::get('/process/upload/complete/{token}', [ProcessFileController::class, 'complete'])->name('process.upload.complete');
    Route::get('/process/upload/preview', [ProcessFileController::class, 'preview'])->name('process.upload.preview');
    Route::get('/process/upload/vip', [ProcessFileController::class, 'vip'])->name('process.upload.vip');
    Route::get('/process/upload/rows', [ProcessFileController::class, 'rows'])->name('process.upload.rows');
    Route::get('/process/upload/export', [ProcessFileController::class, 'exportVip'])->name('process.upload.export');
    Route::get('/process/exclusions', [ExclusionUploadController::class, 'create'])->name('process.exclusions.create');
    Route::get('/process/exclusions/progress/{token}', [ExclusionUploadController::class, 'progress'])->name('process.exclusions.progress');
    Route::get('/process/exclusions/progress/stream/{token}', [ExclusionUploadController::class, 'progressStream'])->name('process.exclusions.progress.stream');
    Route::post('/process/exclusions/chunks/start', [ExclusionUploadController::class, 'startChunkUpload'])->name('process.exclusions.chunks.start');
    Route::post('/process/exclusions/chunks/part', [ExclusionUploadController::class, 'uploadChunk'])->name('process.exclusions.chunks.part');
    Route::post('/process/exclusions/chunks/finish', [ExclusionUploadController::class, 'finishChunkUpload'])->name('process.exclusions.chunks.finish');
    Route::post('/process/exclusions/upload', [ExclusionUploadController::class, 'uploadSingle'])->name('process.exclusions.upload.single');
    Route::delete('/process/exclusions/staged/{token}', [ExclusionUploadController::class, 'destroyStagedUpload'])->name('process.exclusions.staged.destroy');
    Route::post('/process/exclusions/skip', [ExclusionUploadController::class, 'skip'])->name('process.exclusions.skip');
    Route::post('/process/exclusions', [ExclusionUploadController::class, 'store'])->name('process.exclusions.store');

    Route::get('/process/confirm', [App\Http\Controllers\ProcessConfirmController::class, 'create'])->name('process.confirm.create');
    Route::post('/process/confirm', [App\Http\Controllers\ProcessConfirmController::class, 'store'])->name('process.confirm.store');
    Route::get('/process/running', [ProcessRunningController::class, 'show'])->name('process.running.show');

    Route::get('/process/status', [ProcessStatusController::class, 'show'])->name('process.status.current');
    Route::get('/process/status/stream', [ProcessStatusController::class, 'stream'])->name('process.status.stream');
    Route::get('/process/assignments', [AssignmentController::class, 'index'])->name('process.assignments.index');
    Route::get('/process/assignments/reports', [AssignmentController::class, 'reports'])->name('process.assignments.reports');
    Route::get('/process/assignments/report/{process}', [AssignmentController::class, 'report'])->name('process.assignments.report');
    Route::delete('/process/assignments/reports/{process}/cancel', [AssignmentController::class, 'cancel'])->name('process.assignments.cancel');
    Route::get('/process/assignments/exports/status', [AssignmentController::class, 'exportStatus'])->name('process.assignments.exports.status');
    Route::get('/process/assignments/exports/status', [AssignmentController::class, 'exportStatus'])->name('process.assignments.exports.status');
    Route::delete('/process/assignments/reports/bulk', [AssignmentController::class, 'destroyBulk'])->name('process.assignments.destroyBulk');
    Route::delete('/process/assignments/reports/{process}', [AssignmentController::class, 'destroy'])->name('process.assignments.destroy');
    Route::get('/process/assignments/download/{group}/{bucket}', [AssignmentController::class, 'download'])->name('process.assignments.download');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/admin/adminconfig', [AdminController::class, 'config'])->name('admin.config');
    Route::post('/admin/users', [AdminController::class, 'createUser'])->name('admin.createUser');
    Route::put('/admin/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('admin.updateUserStatus');
    Route::put('/admin/users/{user}/name', [AdminController::class, 'updateUserName'])->name('admin.updateUserName');
    Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.deleteUser');
    Route::post('/admin/process-queues', [AdminController::class, 'processQueues'])->name('admin.processQueues');
    Route::post('/configurations/billrange', [BillRangeController::class, 'createRange'])->name('configurations.billrange');

    Route::post('/configurations/billrange2', [BillRangeController::class, 'createStaff'])->name('configurations.billarears');

    Route::post('/configurations/mediums', [BillRangeController::class, 'saveMediums'])->name('configurations.mediums');

    Route::prefix('cc')->name('cc.')->middleware('session.cc_user')->group(function () {
        Route::get('/', [CallCenterDashboardController::class, 'index'])->name('dashboard');
        Route::post('/profile/name', [CallCenterUserController::class, 'setName'])->name('profile.setName');

        Route::get('/caller/dashboard', [CallCenterDashboardController::class, 'callerDashboard'])->name('caller.dashboard');
        Route::get('/payments/list', [CallCenterDashboardController::class, 'listPayments'])->name('payments.list');

        Route::get('/assignments', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'index'])->name('assignments.list');
        Route::get('/assignments/manage', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'manage'])->name('assignments.manage');
        Route::post('/assignments/{user}/accept-all', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'acceptAll'])->name('assignments.acceptAll');
        Route::post('/assignments/{user}/reject-all', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'rejectAll'])->name('assignments.rejectAll');
        Route::get('/assignments/{user}/rows', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'userRows'])->name('assignments.userRows');
        Route::get('/assignments/{assignment}/details', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'assignmentDetails'])->name('assignments.details');
        Route::post('/assignments/{id}/claim', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'claim'])->name('assignments.claim');
        Route::post('/assignments/{id}/complete', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'complete'])->name('assignments.complete');
        Route::post('/assignments/{id}/interactions', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'storeInteraction'])->name('assignments.interactions.store');
        Route::post('/assignments/{id}/accept', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'accept'])->name('assignments.accept');
        Route::post('/assignments/{id}/reject', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'reject'])->name('assignments.reject');

        Route::middleware('session.cc_admin')->group(function () {
            Route::get('/users', [CallCenterUserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'createUserForm'])->name('users.create');
            Route::post('/users/super', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'storeUser'])->name('super.store_user');
            Route::post('/users', [CallCenterUserController::class, 'store'])->name('users.store');
            Route::get('/users/assign', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'indexAssign'])->name('users.assign.index');
            Route::get('/users/{ccUser}/edit', [CallCenterUserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{ccUser}', [CallCenterUserController::class, 'update'])->name('users.update');
            Route::put('/users/{ccUser}/disable', [CallCenterUserController::class, 'disable'])->name('users.disable');
            Route::put('/users/{ccUser}/enable', [CallCenterUserController::class, 'enable'])->name('users.enable');
            Route::get('/users/{user}/assign', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'showAssignForm'])->name('users.assign');
            Route::post('/users/{user}/assign', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'storeAssignment'])->name('users.assign.store');
            Route::delete('/users/{ccUser}', [CallCenterUserController::class, 'destroy'])->name('users.destroy');
            Route::get('/reports/history', [CallCenterReportController::class, 'history'])->name('reports.history');
            Route::get('/reports/{report}/summary', [CallCenterReportController::class, 'summary'])->name('reports.summary');
            Route::get('/reports', [CallCenterReportController::class, 'index'])->name('reports');
            Route::get('/reports/agent-details', [CallCenterReportController::class, 'getAgentDetails'])->name('reports.agentDetails');

            // Segment admin routes (replaces cc region admin)
            Route::get('/segment/dashboard', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'dashboard'])->name('segment.dashboard');
            Route::get('/segment/callers', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'callers'])->name('segment.callers');
            Route::get('/segment/callers/create', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'createCallerForm'])->name('segment.callers.create');
            Route::post('/segment/callers', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'storeCaller'])->name('segment.callers.store');
            Route::get('/segment/callers/{user}/edit', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'editCaller'])->name('segment.callers.edit');
            Route::put('/segment/callers/{user}', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'updateCaller'])->name('segment.callers.update');
            Route::delete('/segment/callers/{user}', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'destroyCaller'])->name('segment.callers.destroy');
            Route::put('/segment/callers/{user}/enable', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'enableCaller'])->name('segment.callers.enable');
            Route::put('/segment/callers/{user}/disable', [\App\Http\Controllers\CallCenter\SegmentAdminController::class, 'disableCaller'])->name('segment.callers.disable');

            Route::post('/reports/{report}/distribute', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'distribute'])->name('reports.distribute');
            Route::post('/reports/{report}/recall', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'recall'])->name('reports.recall');
            Route::get('/reports/{report}/recall/preview', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'recallPreview'])->name('reports.recall.preview');
            Route::post('/reports/{report}/reassign', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'reassign'])->name('reports.reassign');
            Route::get('/reports/{report}/download', [CallCenterReportController::class, 'download'])->name('reports.download');

            // Super admin segment management routes (replaces cc.super.regions)
            Route::get('/segments', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'indexSegments'])->name('super.segments');
            Route::get('/segments/{user}/edit', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'editSegmentAdminForm'])->name('super.edit_segment');
            Route::put('/segments/{user}', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'updateSegmentAdmin'])->name('super.update_segment');
            Route::get('/segments/search', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'searchSegments'])->name('super.segments.search');

            // CC super admin can also create RB region admins
            Route::get('/rb-region/create', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'createRbRegionForm'])->name('super.rb_region.create');
            Route::post('/rb-region', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'storeRbRegion'])->name('super.rb_region.store');

            // CC super admin: manage existing RB region admins
            Route::get('/rb-regions', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'indexRbRegions'])->name('super.rb_regions');
            Route::get('/rb-regions/search', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'searchRbRegions'])->name('super.rb_regions.search');
            Route::get('/rb-regions/{user}/edit', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'editRbRegionForm'])->name('super.rb_regions.edit');
            Route::put('/rb-regions/{user}', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'updateRbRegion'])->name('super.rb_regions.update');
        });
    });

    Route::prefix('rb')->name('rb.')->middleware('session.rb_user')->group(function () {
        Route::get('/', [\App\Http\Controllers\RegionalBilling\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/caller/dashboard', [\App\Http\Controllers\RegionalBilling\DashboardController::class, 'callerDashboard'])->name('caller.dashboard');
        Route::get('/assignments', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'index'])->name('assignments.list');
        Route::get('/assignments/manage', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'manage'])->name('assignments.manage');
        Route::post('/assignments/{user}/accept-all', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'acceptAll'])->name('assignments.acceptAll');
        Route::post('/assignments/{user}/reject-all', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'rejectAll'])->name('assignments.rejectAll');
        Route::get('/assignments/{user}/rows', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'userRows'])->name('assignments.userRows');
        Route::get('/assignments/{assignment}/details', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'assignmentDetails'])->name('assignments.details');
        Route::post('/assignments/{id}/claim', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'claim'])->name('assignments.claim');
        Route::post('/assignments/{id}/complete', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'complete'])->name('assignments.complete');
        Route::post('/assignments/{id}/interactions', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'storeInteraction'])->name('assignments.interactions.store');
        Route::post('/assignments/{id}/accept', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'accept'])->name('assignments.accept');
        Route::post('/assignments/{id}/reject', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'reject'])->name('assignments.reject');
        Route::get('/supervisor/dashboard', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'supervisorDashboard'])->name('supervisor.dashboard');
        Route::get('/rtom/dashboard', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'rtomDashboard'])->name('rtom.dashboard');

        Route::middleware('session.rb_user')->group(function () {
            Route::get('/users', [\App\Http\Controllers\RegionalBilling\UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [\App\Http\Controllers\RegionalBilling\SuperAdminController::class, 'createUserForm'])->name('users.create');
            Route::post('/users/super', [\App\Http\Controllers\RegionalBilling\SuperAdminController::class, 'storeUser'])->name('super.store_user');
            Route::post('/users', [\App\Http\Controllers\RegionalBilling\UserController::class, 'store'])->name('users.store');
            Route::get('/users/assign', [\App\Http\Controllers\RegionalBilling\SuperAdminController::class, 'indexAssign'])->name('users.assign.index');
            Route::get('/users/{user}/edit', [\App\Http\Controllers\RegionalBilling\UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [\App\Http\Controllers\RegionalBilling\UserController::class, 'update'])->name('users.update');
            Route::put('/users/{user}/disable', [\App\Http\Controllers\RegionalBilling\UserController::class, 'disable'])->name('users.disable');
            Route::put('/users/{user}/enable', [\App\Http\Controllers\RegionalBilling\UserController::class, 'enable'])->name('users.enable');
            Route::get('/users/{user}/assign', [\App\Http\Controllers\RegionalBilling\SuperAdminController::class, 'showAssignForm'])->name('users.assign');
            Route::post('/users/{user}/assign', [\App\Http\Controllers\RegionalBilling\SuperAdminController::class, 'storeAssignment'])->name('users.assign.store');
            Route::delete('/users/{user}', [\App\Http\Controllers\RegionalBilling\UserController::class, 'destroy'])->name('users.destroy');
            Route::get('/reports/history', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'history'])->name('reports.history');
            Route::get('/reports/{report}/summary', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'summary'])->name('reports.summary');
            Route::get('/reports', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'index'])->name('reports');
            Route::post('/reports/review-preference', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'updateReviewPreference'])->name('reports.preference');
            Route::post('/reports/{report}/exclude-file', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'submitExcludeFile'])->name('reports.exclude_file');
            Route::post('/reports/{report}/include-file', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'submitIncludeFile'])->name('reports.include_file');
            Route::post('/reports/{report}/rows/hide', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'hideRows'])->name('reports.hide_rows');
            Route::post('/reports/{report}/unlock', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'unlockReview'])->name('reports.unlock');
            Route::post('/reports/{report}/pass', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'passReport'])->name('reports.pass');
            Route::get('/reports/agent-details', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'getAgentDetails'])->name('reports.agentDetails');
            Route::get('/regions', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'indexRegions'])->name('regions.index');
            Route::get('/regions/{user}/edit', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'editRegionAdminForm'])->name('regions.edit');
            Route::put('/regions/{user}', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'updateRegionAdmin'])->name('regions.update');
            Route::get('/regions/search', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'searchRegions'])->name('regions.search');
            Route::get('/regions/dashboard', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'dashboard'])->name('region.dashboard');
            Route::get('/rtoms/dashboard', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'index'])->name('region.index');
            Route::get('/rtoms/search', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'search'])->name('region.search');
            Route::get('/rtoms/create-admin', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'createAdminForm'])->name('region.create_admin');
            Route::get('/rtoms/create-supervisor', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'createSupervisorForm'])->name('region.create_supervisor');
            Route::post('/rtoms/admins', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'storeAdmin'])->name('region.store_admin');
            Route::post('/rtoms/supervisors', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'storeSupervisor'])->name('region.store_supervisor');
            Route::get('/rtoms/admins/{user}/edit', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'editAdminForm'])->name('region.edit_admin');
            Route::put('/rtoms/admins/{user}', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'updateAdmin'])->name('region.update_admin');
            Route::get('/rtoms/supervisors/{user}/edit', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'editSupervisorForm'])->name('region.edit_supervisor');
            Route::put('/rtoms/supervisors/{user}', [\App\Http\Controllers\RegionalBilling\RegionAdminController::class, 'updateSupervisor'])->name('region.update_supervisor');
            Route::post('/reports/{report}/distribute', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'distribute'])->name('reports.distribute');
            Route::post('/reports/{report}/distribute-supervisor', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'distributeSupervisor'])->name('reports.distribute_supervisor');
            Route::get('/reports/{report}/distribute/cancel/{token}', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'cancelDistribute'])->name('reports.distribute.cancel');
            Route::post('/reports/{report}/recall', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'recall'])->name('reports.recall');
            Route::get('/reports/{report}/recall/preview', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'recallPreview'])->name('reports.recall.preview');
            Route::post('/reports/{report}/reassign', [\App\Http\Controllers\RegionalBilling\AssignmentController::class, 'reassign'])->name('reports.reassign');
            Route::get('/reports/{report}/download', [\App\Http\Controllers\RegionalBilling\ReportController::class, 'download'])->name('reports.download');
        });
    });
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');

Route::get('/auth/microsoft', [AuthController::class, 'microsoftRedirect']);
Route::get('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback'])->name('microsoft.callback');
