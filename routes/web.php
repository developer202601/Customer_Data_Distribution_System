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
    Route::get('/process/upload', [ProcessFileController::class, 'create'])->name('process.upload.create');
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
    Route::post('/process/exclusions', [ExclusionUploadController::class, 'store'])->name('process.exclusions.store');
    
    Route::get('/process/confirm', [App\Http\Controllers\ProcessConfirmController::class, 'create'])->name('process.confirm.create');
    Route::post('/process/confirm', [App\Http\Controllers\ProcessConfirmController::class, 'store'])->name('process.confirm.store');
    Route::get('/process/running', [ProcessRunningController::class, 'show'])->name('process.running.show');
    
    Route::get('/process/status', [ProcessStatusController::class, 'show'])->name('process.status.current');
    Route::get('/process/status/stream', [ProcessStatusController::class, 'stream'])->name('process.status.stream');
    Route::get('/process/assignments', [AssignmentController::class, 'index'])->name('process.assignments.index');
    Route::get('/process/assignments/reports', [AssignmentController::class, 'reports'])->name('process.assignments.reports');
    Route::get('/process/assignments/report/{process}', [AssignmentController::class, 'report'])->name('process.assignments.report');
    Route::delete('/process/assignments/reports/bulk', [AssignmentController::class, 'destroyBulk'])->name('process.assignments.destroyBulk');
    Route::delete('/process/assignments/reports/{process}', [AssignmentController::class, 'destroy'])->name('process.assignments.destroy');
    // Consolidated into overview; group-specific pages removed
    Route::get('/process/assignments/download/{group}/{bucket}', [AssignmentController::class, 'download'])->name('process.assignments.download');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/admin/adminconfig', [AdminController::class, 'config'])->name('admin.config');
    Route::post('/admin/users', [AdminController::class, 'createUser'])->name('admin.createUser');
    Route::put('/admin/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('admin.updateUserStatus');
    Route::put('/admin/users/{user}/name', [AdminController::class, 'updateUserName'])->name('admin.updateUserName');
    Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.deleteUser');
    Route::post('/configurations/billrange', [BillRangeController::class, 'createRange'])->name('configurations.billrange');
    
    Route::post('/configurations/billrange2', [BillRangeController::class, 'createStaff'])->name('configurations.billarears');

    Route::prefix('cc')->name('cc.')->middleware('session.cc_user')->group(function () {
        Route::get('/', [CallCenterDashboardController::class, 'index'])->name('dashboard');
        // allow callers to set their display name on first login
        Route::post('/profile/name', [CallCenterUserController::class, 'setName'])->name('profile.setName');
        Route::get('/payments/list', [CallCenterDashboardController::class, 'paymentList'])->name('payments.list');
        Route::get('/caller/{id}/calls7', [CallCenterDashboardController::class, 'callerCalls7'])->name('caller.calls7');

        // Supervisor dashboard
        Route::get('/supervisor/dashboard', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'supervisorDashboard'])->name('supervisor.dashboard');

        // RTOM dashboard
        Route::get('/rtom/dashboard', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'rtomDashboard'])->name('rtom.dashboard');

        // Call center staff assignment endpoints
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
            // Region admin pages (RTOM management)
            Route::get('/rtoms/dashboard', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'dashboard'])->name('region.dashboard');
            Route::get('/rtoms/reports/review', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'reviewReport'])->name('region.review');
            Route::post('/rtoms/reports/{report}/rows/hide', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'hideRows'])->name('region.review.hide_rows');
            Route::post('/rtoms/reports/{report}/pass', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'passReport'])->name('region.review.pass');
            Route::post('/rtoms/review-preference', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'updateReviewPreference'])->name('region.review.preference');
            Route::get('/rtoms', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'index'])->name('region.index');
            Route::get('/rtoms/search', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'search'])->name('region.search');
            Route::get('/rtoms/assign', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'indexAssign'])->name('region.assign.index');
            Route::get('/rtoms/{user}/assign', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'showAssignForm'])->name('region.assign');
            Route::post('/rtoms/{user}/assign', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'storeAssignment'])->name('region.assign.store');
            Route::get('/rtoms/create-admin', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'createAdminForm'])->name('region.create_admin');
            Route::get('/rtoms/create-supervisor', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'createSupervisorForm'])->name('region.create_supervisor');
            Route::post('/rtoms/admins', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'storeAdmin'])->name('region.store_admin');
            Route::post('/rtoms/supervisors', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'storeSupervisor'])->name('region.store_supervisor');
            Route::get('/rtoms/admins/{user}/edit', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'editAdminForm'])->name('region.edit_admin');
            Route::put('/rtoms/admins/{user}', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'updateAdmin'])->name('region.update_admin');
            Route::delete('/rtoms/admins/{user}', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'destroyAdmin'])->name('region.destroy_admin');
            // Supervisor management (for RTOM users)
            Route::get('/rtoms/supervisors/{user}/edit', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'editSupervisorForm'])->name('region.edit_supervisor');
            Route::put('/rtoms/supervisors/{user}', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'updateSupervisor'])->name('region.update_supervisor');
            Route::put('/rtoms/supervisors/{user}/disable', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'disableSupervisor'])->name('region.disable_supervisor');
            Route::put('/rtoms/supervisors/{user}/enable', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'enableSupervisor'])->name('region.enable_supervisor');
            Route::delete('/rtoms/supervisors/{user}', [\App\Http\Controllers\CallCenter\RegionAdminController::class, 'destroySupervisor'])->name('region.destroy_supervisor');
            // Super admin pages (Region management)
            Route::get('/regions', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'indexRegions'])->name('super.regions');
            Route::get('/regions/{user}/edit', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'editRegionAdminForm'])->name('super.edit_region');
            Route::put('/regions/{user}', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'updateRegionAdmin'])->name('super.update_region');
            Route::get('/regions/search', [\App\Http\Controllers\CallCenter\SuperAdminController::class, 'searchRegions'])->name('super.regions.search');
            // Region admins create RTOM admins only; regular user creation removed
            Route::post('/reports/{report}/distribute', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'distribute'])->name('reports.distribute');
            Route::post('/reports/{report}/distribute-supervisor', [\App\Http\Controllers\CallCenter\ReportController::class, 'distributeSupervisor'])->name('reports.distribute_supervisor');
            Route::get('/reports/{report}/distribute/cancel/{token}', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'cancelDistribute'])->name('reports.distribute.cancel');
            Route::post('/reports/{report}/recall', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'recall'])->name('reports.recall');
            Route::get('/reports/{report}/recall/preview', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'recallPreview'])->name('reports.recall.preview');
            Route::post('/reports/{report}/reassign', [\App\Http\Controllers\CallCenter\AssignmentController::class, 'reassign'])->name('reports.reassign');
            Route::get('/reports/{report}/download', [CallCenterReportController::class, 'download'])->name('reports.download');
        });
    });
    
    
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');

//Route::post('/create/range', [AdminController::class, 'createRange'])->name('create.range');
