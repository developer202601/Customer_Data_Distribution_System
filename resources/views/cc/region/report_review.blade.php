@extends('layouts.cc')

@section('title', 'Review Report Rows')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center — Region: {{ $region }}</p>
                        <h1 class="process-upload-title mb-0">Review Report Rows</h1>
                        <p class="text-muted small mb-0">Hide unwanted rows before they are distributed to supervisors and callers.</p>
                    </div>
                    <a href="{{ route('cc.region.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-4">Back to Dashboard</a>
                </div>

                <div class="card border-0 bg-light rounded-4 mb-4">
                    <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">Regional Review Gate</p>
                            <p class="mb-0 small text-muted">Enable this to use this page and require regional review before distribution.</p>
                            @if(!empty($reviewOptIn) && !empty($reviewEnabledAt))
                                <p class="mb-0 small text-muted">Only reports generated on/after {{ $reviewEnabledAt->format('Y-m-d H:i') }} can be reviewed here.</p>
                            @endif
                        </div>
                        <form method="post" action="{{ route('cc.region.review.preference') }}" class="d-flex align-items-center gap-2 ms-auto">
                            @csrf
                            <input type="hidden" name="enable_regional_review" value="{{ !empty($reviewOptIn) ? '0' : '1' }}">
                            <span class="badge {{ !empty($reviewOptIn) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ !empty($reviewOptIn) ? 'Enabled' : 'Disabled' }}</span>
                            <button type="submit" class="btn btn-sm {{ !empty($reviewOptIn) ? 'btn-outline-danger' : 'btn-outline-success' }} rounded-pill px-3">
                                {{ !empty($reviewOptIn) ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div id="reviewWorkspace" class="{{ empty($reviewOptIn) ? 'review-workspace-disabled' : '' }}">
                    @if(empty($reviewOptIn))
                        <div class="alert alert-secondary mb-3">
                            Review page is disabled while Regional Review Gate is off. Enable it above to use this page.
                        </div>
                    @endif

                <form method="get" id="reviewFiltersForm" class="row g-2 align-items-end mb-3" data-loader-off="1">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Report</label>
                        <select class="form-select form-select-sm" name="report">
                            @forelse($reports as $report)
                                @php
                                    $dm = $report->dataset_month;
                                    $label = ($dm && strlen($dm) === 6)
                                        ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report'
                                        : ('Report #' . $report->id);
                                @endphp
                                <option value="{{ $report->id }}" {{ optional($selectedReport)->id === $report->id ? 'selected' : '' }}>{{ $label }}</option>
                            @empty
                                <option value="">No reports</option>
                            @endforelse
                        </select>
                    </div>
                    <input type="hidden" id="show_hidden" name="show_hidden" value="{{ !empty($showHidden) ? '1' : '0' }}">
                    <input type="hidden" id="show_hidden_only" name="show_hidden_only" value="{{ !empty($showHiddenOnly) ? '1' : '0' }}">
                </form>

                @if($selectedReport)
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="card border-0 bg-light rounded-4 h-100">
                                <div class="card-body">
                                    <p class="text-uppercase text-muted small mb-1">Total rows</p>
                                    <h2 class="h5 mb-0" id="countTotalRows">{{ number_format($counts['total'] ?? 0) }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light rounded-4 h-100">
                                <div class="card-body">
                                    <p class="text-uppercase text-muted small mb-1">Visible for distribution</p>
                                    <h2 class="h5 mb-0" id="countVisibleRows">{{ number_format($counts['visible'] ?? 0) }}</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light rounded-4 h-100">
                                <div class="card-body">
                                    <p class="text-uppercase text-muted small mb-1">Hidden rows</p>
                                    <h2 class="h5 mb-0" id="countHiddenRows">{{ number_format($counts['hidden'] ?? 0) }}</h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="card border-0 bg-light rounded-4">
                            <div class="card-body p-3">
                                <p class="text-uppercase text-muted small mb-2">Attach File</p>
                                <form method="post" action="#" enctype="multipart/form-data" class="d-flex gap-2 align-items-center mb-0">
                                    @csrf
                                    <input type="file" name="attach_file" id="attachFileInput" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" />
                                    <button type="submit" class="btn btn-primary btn-sm px-3" style="white-space: nowrap;">Attach File</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                        <form method="post" action="{{ route('cc.region.review.pass', $selectedReport->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Pass to Supervisors</button>
                        </form>
                        @if(!empty($reviewRecord?->reviewed_at))
                            <span class="small text-success">Passed at {{ $reviewRecord->reviewed_at->format('Y-m-d H:i') }}</span>
                        @else
                            <span class="small text-muted">This report has not been passed yet for this region.</span>
                        @endif

                        <div class="card border-0 bg-light rounded-4 mb-3 w-100">
                            <div class="card-body p-3">
                                <p class="text-uppercase text-muted small mb-1">Exclude file submission</p>
                                <form method="post" action="#" enctype="multipart/form-data" class="d-flex gap-2 align-items-center mb-0">
                                    @csrf
                                    <input type="file" name="exclude_file" class="form-control form-control-sm" />
                                    <button type="submit" class="btn btn-secondary btn-sm px-2 py-1" style="white-space: nowrap;">Submit Exclude File</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div id="bulkActionsDock" class="bulk-actions-dock mb-3" data-locked="{{ !empty($reviewRecord?->reviewed_at) ? '1' : '0' }}">
                        <div class="bulk-actions-card" id="bulkActionsCard">
                            <div class="small text-muted mb-1" id="bulkActionsSelectionHint">Select rows to manage visibility.</div>
                            <div class="small text-warning-emphasis mb-2 d-none" id="bulkActionsMixedHint">Both visible and hidden rows are selected. Choose the correct action below.</div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn btn-outline-danger btn-sm bulk-action-btn d-none" data-action="hide" id="bulkHideBtn">Hide Selected Rows</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm bulk-action-btn d-none" data-action="unhide" id="bulkUnhideBtn">Unhide Selected Rows</button>
                                <button type="button" class="btn btn-outline-primary btn-sm d-none" id="bulkClearSelectionBtn">Clear selection</button>
                                <span class="small text-muted d-none" id="bulkActionsCountBadge"></span>
                            </div>
                        </div>
                    </div>

                    <form id="bulkRowsForm" method="post" action="{{ route('cc.region.review.hide_rows', $selectedReport->id) }}">
                        @csrf
                        <input type="hidden" name="action" id="bulkAction" value="hide">
                        <div id="reviewTableContainer" data-loader-off="1">
                            @include('cc.region._report_review_table', ['selectedReport' => $selectedReport, 'rows' => $rows, 'showHidden' => $showHidden, 'showHiddenOnly' => $showHiddenOnly, 'isLocked' => !empty($reviewRecord?->reviewed_at)])
                        </div>
                    </form>

                    <div class="modal fade" id="bulkActionConfirmModal" tabindex="-1" aria-labelledby="bulkActionConfirmTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-scrollable align-content-center">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="bulkActionConfirmTitle">Confirm row visibility update</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted mb-2" id="bulkActionConfirmDescription"></p>
                                    <div class="border rounded p-2 bg-light" style="max-height: 220px; overflow-y: auto;">
                                        <ol class="small mb-0 ps-3" id="bulkActionConfirmList"></ol>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary btn-sm" id="bulkActionConfirmSubmit">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    @if(!empty($reviewOptIn) && !empty($reviewEnabledAt))
                        <div class="alert alert-info mb-0">No reviewable reports found for your region after {{ $reviewEnabledAt->format('Y-m-d H:i') }}.</div>
                    @else
                        <div class="alert alert-info mb-0">No report rows are currently available for your region.</div>
                    @endif
                @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style nonce="{{ $cspNonce ?? '' }}">
    .review-workspace-disabled {
        opacity: 0.45;
        pointer-events: none;
        user-select: none;
        filter: grayscale(35%);
    }

    /* Compact pagination / summary styling for the review table */
    #reviewRowsPagination {
        font-size: 0.85rem;
    }

    #reviewRowsPagination .pagination {
        margin-bottom: 0;
        margin-top: 0;
    }

    #reviewRowsPagination .pagination .page-link {
        padding: 0.25rem 0.5rem;
    }

    /* AGGRESSIVE Dark Mode Overrides for theme-dark class */
    @media (prefers-color-scheme: dark) {
        .card.border-0.bg-light {
            background-color: #2d3748 !important;
            color: #e2e8f0 !important;
        }

        .card.border-0.bg-light .card-body h2,
        .card.border-0.bg-light .card-body p {
            color: #e2e8f0 !important;
        }

        .card.border-0.bg-light .text-muted {
            color: #a0aec0 !important;
        }

        .card.border-0.bg-light .text-uppercase {
            color: #cbd5e0 !important;
        }

        /* Stats cards specifically */
        .row.g-3.mb-3 .col-md-4 .card.bg-light {
            background-color: #2d3748 !important;
        }

        .row.g-3.mb-3 .col-md-4 .card h2 {
            color: #e2e8f0 !important;
        }

        .row.g-3.mb-3 .col-md-4 .card p {
            color: #a0aec0 !important;
        }
    }

    /* Dark theme class selector (for body.theme-dark) */
    body.theme-dark .bg-light {
        background-color: #2d3748 !important;
        color: #e2e8f0 !important;
    }

    body.theme-dark .card {
        background-color: #1a202c !important;
        border-color: #4a5568 !important;
    }

    body.theme-dark .card-body {
        color: #e2e8f0 !important;
    }

    body.theme-dark .text-muted {
        color: #a0aec0 !important;
    }

    body.theme-dark .text-uppercase {
        color: #cbd5e0 !important;
    }

    body.theme-dark .form-control,
    body.theme-dark .form-select {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    body.theme-dark input[type="file"],
    body.theme-dark input[type="file"]::file-selector-button {
        background-color: #2d3748 !important;
        color: #e2e8f0 !important;
        border-color: #4a5568 !important;
    }

    body.theme-dark input[type="file"]::file-selector-button {
        background-color: #4a5568 !important;
        color: #e2e8f0 !important;
        border: 1px solid #4a5568 !important;
    }

    body.theme-dark input[type="text"],
    body.theme-dark input[type="email"],
    body.theme-dark input[type="password"],
    body.theme-dark textarea {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    body.theme-dark .form-control::placeholder {
        color: #718096 !important;
    }

    body.theme-dark .form-control:focus,
    body.theme-dark .form-select:focus,
    body.theme-dark input[type="file"]:focus {
        background-color: #2d3748 !important;
        border-color: #667eea !important;
        color: #e2e8f0 !important;
    }

    body.theme-dark input[type="file"]::placeholder {
        color: #718096 !important;
    }

    body.theme-dark h1,
    body.theme-dark h2,
    body.theme-dark h3,
    body.theme-dark h4,
    body.theme-dark h5,
    body.theme-dark h6 {
        color: #e2e8f0 !important;
    }

    body.theme-dark p {
        color: #cbd5e0 !important;
    }

    body.theme-dark label {
        color: #a0aec0 !important;
    }

    body.theme-dark .form-label {
        color: #a0aec0 !important;
    }

    body.theme-dark .badge {
        background-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    body.theme-dark .bulk-actions-card {
        background: rgba(45, 55, 72, 0.98) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        box-shadow: 0 0.35rem 0.85rem rgba(0, 0, 0, 0.4);
        color: #e2e8f0 !important;
    }

    body.theme-dark .alert-secondary {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #cbd5e0 !important;
    }

    /* Bootstrap form styling overrides */
    body.theme-dark .form-control:not(:focus) {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    body.theme-dark .input-group .form-control {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    .bulk-actions-dock {
        position: relative;
        z-index: 1020;
    }

    .bulk-actions-dock.bulk-actions-floating {
        position: fixed;
        left: 50%;
        bottom: 1rem;
        transform: translateX(-50%);
        z-index: 1030;
        width: min(92vw, 720px);
        margin-bottom: 0;
    }

    body.modal-open .bulk-actions-dock {
        opacity: 0;
        pointer-events: none;
    }

    .bulk-actions-card {
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.85rem;
        box-shadow: 0 0.35rem 0.85rem rgba(0, 0, 0, 0.08);
        padding: 0.65rem 0.75rem;
    }

    .bulk-action-btn {
        position: relative;
        padding-left: 1.1rem;
    }

    .bulk-action-btn::before {
        content: '';
        position: absolute;
        left: 0.4rem;
        top: 0.4rem;
        bottom: 0.4rem;
        width: 0.22rem;
        border-radius: 0.25rem;
        background-color: var(--bulk-action-accent, rgba(13, 110, 253, 0.85));
    }

    .bulk-action-btn[data-action="hide"] {
        --bulk-action-accent: rgba(220, 53, 69, 0.85);
    }

    .bulk-action-btn[data-action="unhide"] {
        --bulk-action-accent: rgba(25, 135, 84, 0.85);
    }

    /* Dark mode for Bootstrap's data-bs-theme attribute */
    [data-bs-theme="dark"] .bg-light {
        background-color: #2d3748 !important;
        color: #e2e8f0 !important;
    }

    @media (max-width: 768px) {
        .bulk-actions-dock.bulk-actions-floating {
            bottom: 0.75rem;
            width: calc(100vw - 1.5rem);
        }

        .bulk-actions-card {
            padding: 0.6rem;
        }
    }
</style>
@endpush

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('reviewFiltersForm');
    const bulkForm = document.getElementById('bulkRowsForm');
    const tableContainer = document.getElementById('reviewTableContainer');
    const countTotal = document.getElementById('countTotalRows');
    const countVisible = document.getElementById('countVisibleRows');
    const countHidden = document.getElementById('countHiddenRows');
    const bulkActionsDock = document.getElementById('bulkActionsDock');
    const bulkActionsCard = document.getElementById('bulkActionsCard');
    const bulkHideBtn = document.getElementById('bulkHideBtn');
    const bulkUnhideBtn = document.getElementById('bulkUnhideBtn');
    const bulkClearSelectionBtn = document.getElementById('bulkClearSelectionBtn');
    const bulkActionsSelectionHint = document.getElementById('bulkActionsSelectionHint');
    const bulkActionsMixedHint = document.getElementById('bulkActionsMixedHint');
    const bulkActionsCountBadge = document.getElementById('bulkActionsCountBadge');
    const bulkActionConfirmModalEl = document.getElementById('bulkActionConfirmModal');
    const bulkActionConfirmDescription = document.getElementById('bulkActionConfirmDescription');
    const bulkActionConfirmList = document.getElementById('bulkActionConfirmList');
    const bulkActionConfirmSubmit = document.getElementById('bulkActionConfirmSubmit');
    const hideRowsUrl = '{{ route('cc.region.review.hide_rows', $selectedReport->id) }}';
    let searchTimer = null;
    let currentPage = 1;
    let bulkConfirmModal = null;
    let pendingBulkAction = null;
    let pendingRowIds = [];
    let bulkDockAnchorTop = null;
    const selectedRows = new Map();
    let lastSearchCaret = null;

    function getRowInfoFromElement(rowEl) {
        if (!rowEl) return null;
        const check = rowEl.querySelector('input.row-check');
        if (!check) return null;
        return {
            id: String(check.value || rowEl.getAttribute('data-row-id') || '').trim(),
            checked: !!check.checked,
            visibility: rowEl.getAttribute('data-row-visibility') === 'hidden' ? 'hidden' : 'visible',
            label: (rowEl.getAttribute('data-row-label') || ('Row #' + (check.value || ''))).trim(),
        };
    }

    function getSelectedRowsByType() {
        const selected = Array.from(selectedRows.values());
        return {
            all: selected,
            visible: selected.filter(function (row) { return row.visibility === 'visible'; }),
            hidden: selected.filter(function (row) { return row.visibility === 'hidden'; }),
        };
    }

    function syncCurrentPageSelectionFromMap() {
        const rows = Array.from(document.querySelectorAll('#reviewRowsTable .review-row'));
        const checks = Array.from(document.querySelectorAll('#reviewRowsTable .row-check'));

        rows.forEach(function (row) {
            const rowInfo = getRowInfoFromElement(row);
            if (!rowInfo || !rowInfo.id) return;
            if (selectedRows.has(rowInfo.id)) {
                selectedRows.set(rowInfo.id, {
                    id: rowInfo.id,
                    visibility: rowInfo.visibility,
                    label: rowInfo.label,
                });
            }
        });

        checks.forEach(function (cb) {
            const rowId = String(cb.value || '').trim();
            cb.checked = rowId !== '' && selectedRows.has(rowId);
        });

        const selectAll = document.getElementById('selectAllRows');
        if (selectAll) {
            const selectedCount = checks.filter(function (cb) { return cb.checked; }).length;
            const allSelected = checks.length > 0 && selectedCount === checks.length;
            selectAll.checked = allSelected;
            selectAll.indeterminate = selectedCount > 0 && !allSelected;
        }
    }

    function removeRowsFromSelection(rowIds) {
        rowIds.forEach(function (id) {
            selectedRows.delete(String(id));
        });
    }

    function updateBulkActionsUI() {
        if (!bulkActionsDock || !bulkActionsCard) return;

        const isLocked = bulkActionsDock.getAttribute('data-locked') === '1';
        const groups = getSelectedRowsByType();
        const visibleCount = groups.visible.length;
        const hiddenCount = groups.hidden.length;
        const totalCount = groups.all.length;

        if (isLocked) {
            bulkActionsSelectionHint.textContent = 'Review is locked. Row visibility cannot be changed.';
            bulkActionsMixedHint.classList.add('d-none');
            bulkHideBtn.classList.add('d-none');
            bulkUnhideBtn.classList.add('d-none');
            bulkClearSelectionBtn.classList.add('d-none');
            bulkActionsCountBadge.classList.add('d-none');
            return;
        }

        if (totalCount === 0) {
            bulkActionsSelectionHint.textContent = 'Select rows to manage visibility.';
            bulkActionsMixedHint.classList.add('d-none');
            bulkHideBtn.classList.add('d-none');
            bulkUnhideBtn.classList.add('d-none');
            bulkClearSelectionBtn.classList.add('d-none');
            bulkActionsCountBadge.classList.add('d-none');
            return;
        }

        bulkActionsSelectionHint.textContent = 'Selected rows: ' + totalCount.toLocaleString() + '.';
        bulkHideBtn.classList.toggle('d-none', visibleCount === 0);
        bulkUnhideBtn.classList.toggle('d-none', hiddenCount === 0);
        bulkClearSelectionBtn.classList.remove('d-none');

        const mixed = visibleCount > 0 && hiddenCount > 0;
        bulkActionsMixedHint.classList.toggle('d-none', !mixed);

        bulkActionsCountBadge.classList.remove('d-none');
        bulkActionsCountBadge.textContent = 'Visible: ' + visibleCount.toLocaleString() + ' | Hidden: ' + hiddenCount.toLocaleString();
    }

    function measureBulkDockAnchorTop() {
        if (!bulkActionsDock) return;
        const hadFloatingClass = bulkActionsDock.classList.contains('bulk-actions-floating');
        if (hadFloatingClass) {
            bulkActionsDock.classList.remove('bulk-actions-floating');
        }

        bulkDockAnchorTop = bulkActionsDock.getBoundingClientRect().top + window.scrollY;

        if (hadFloatingClass) {
            bulkActionsDock.classList.add('bulk-actions-floating');
        }
    }

    function updateBulkDockPosition() {
        if (!bulkActionsDock) return;
        if (bulkDockAnchorTop === null) {
            measureBulkDockAnchorTop();
        }

        const nav = document.querySelector('.navbar.fixed-top, .navbar.sticky-top, .navbar');
        const navBottom = nav ? nav.getBoundingClientRect().bottom : 0;
        const triggerY = window.scrollY + navBottom + 8;
        const shouldFloat = triggerY >= bulkDockAnchorTop;
        bulkActionsDock.classList.toggle('bulk-actions-floating', shouldFloat);
    }

    function initConfirmModal() {
        if (!bulkActionConfirmModalEl || typeof bootstrap === 'undefined') return;
        bulkConfirmModal = new bootstrap.Modal(bulkActionConfirmModalEl, {
            backdrop: 'static',
            keyboard: false,
        });
    }

    function openBulkConfirm(action, targetRows) {
        if (!bulkConfirmModal || !bulkActionConfirmDescription || !bulkActionConfirmList || !bulkActionConfirmSubmit) return false;

        pendingBulkAction = action;
        pendingRowIds = targetRows.map(function (row) { return row.id; });

        const actionLabel = action === 'unhide' ? 'unhide' : 'hide';
        bulkActionConfirmDescription.textContent = 'You are about to ' + actionLabel + ' ' + targetRows.length + ' row(s):';
        bulkActionConfirmSubmit.textContent = 'Confirm ' + (action === 'unhide' ? 'Unhide' : 'Hide');
        bulkActionConfirmSubmit.classList.toggle('btn-success', action === 'unhide');
        bulkActionConfirmSubmit.classList.toggle('btn-danger', action !== 'unhide');

        bulkActionConfirmList.innerHTML = '';
        targetRows.forEach(function (row) {
            const li = document.createElement('li');
            li.className = 'mb-1';
            li.textContent = row.label;
            bulkActionConfirmList.appendChild(li);
        });

        bulkConfirmModal.show();
        return true;
    }

    function buildBulkActionFormData(action, rowIds) {
        const formData = new FormData();
        const tokenInput = bulkForm ? bulkForm.querySelector('input[name="_token"]') : null;
        if (tokenInput && tokenInput.value) {
            formData.append('_token', tokenInput.value);
        }
        formData.append('action', action === 'unhide' ? 'unhide' : 'hide');
        rowIds.forEach(function (id) {
            formData.append('row_ids[]', String(id));
        });

        const searchEl = document.getElementById('tableSearch');
        if (searchEl) formData.append('q', searchEl.value || '');
        return formData;
    }

    async function submitBulkAction(action, rowIds) {
        const submitButtons = Array.from(document.querySelectorAll('.bulk-action-btn, #bulkActionConfirmSubmit, #bulkActionConfirmModal button[data-bs-dismiss="modal"]'));
        submitButtons.forEach(function (b) { b.disabled = true; });

        const formData = buildBulkActionFormData(action, rowIds);

        try {
            const res = await fetch(hideRowsUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: formData,
            });

            const json = await res.json().catch(function () { return null; });

            if (!res.ok) {
                const msg = (json?.errors ? Object.values(json.errors).flat().join(' ') : (json?.message || 'Unable to update rows.'));
                showTopToast(msg, true);
            } else {
                if (json?.message) {
                    showTopToast(json.message);
                }
                removeRowsFromSelection(rowIds);
                await fetchTable(currentPage);
            }
        } catch (err) {
            showTopToast('Unable to update rows.', true);
        } finally {
            submitButtons.forEach(function (b) { b.disabled = false; });
        }
    }

    function applyRowBindings() {
        const selectAll = document.getElementById('selectAllRows');
        const checks = Array.from(document.querySelectorAll('.row-check'));

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checks.forEach(function (cb) {
                    cb.checked = selectAll.checked;

                    const row = cb.closest('.review-row');
                    const rowInfo = getRowInfoFromElement(row);
                    if (!rowInfo || !rowInfo.id) return;

                    if (cb.checked) {
                        selectedRows.set(rowInfo.id, {
                            id: rowInfo.id,
                            visibility: rowInfo.visibility,
                            label: rowInfo.label,
                        });
                    } else {
                        selectedRows.delete(rowInfo.id);
                    }
                });

                selectAll.indeterminate = false;
                updateBulkActionsUI();
            });
        }

        checks.forEach(function (cb) {
            cb.addEventListener('change', function () {
                const row = cb.closest('.review-row');
                const rowInfo = getRowInfoFromElement(row);
                if (rowInfo && rowInfo.id) {
                    if (cb.checked) {
                        selectedRows.set(rowInfo.id, {
                            id: rowInfo.id,
                            visibility: rowInfo.visibility,
                            label: rowInfo.label,
                        });
                    } else {
                        selectedRows.delete(rowInfo.id);
                    }
                }

                if (selectAll) {
                    const selectedCount = checks.filter(function (entry) { return entry.checked; }).length;
                    const allSelected = checks.length > 0 && selectedCount === checks.length;
                    selectAll.checked = allSelected;
                    selectAll.indeterminate = selectedCount > 0 && !allSelected;
                }

                updateBulkActionsUI();
            });
        });

        document.querySelectorAll('#reviewRowsTable .review-row').forEach(function (row) {
            row.addEventListener('click', function (ev) {
                if (ev.target.closest('input.row-check')) return;
                if (ev.target.closest('.row-details-btn')) return;
                const cb = row.querySelector('input.row-check');
                if (!cb) return;
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        document.querySelectorAll('#reviewRowsTable .row-details-btn').forEach(function (btn) {
            const targetId = btn.getAttribute('data-target-row-id');
            if (!targetId) return;
            const target = document.getElementById(targetId);
            if (!target) return;

            const collapsedText = btn.getAttribute('data-collapsed-text') || 'More details';
            const expandedText = btn.getAttribute('data-expanded-text') || 'Collapse details';

            const syncState = function () {
                const isExpanded = !target.classList.contains('d-none');
                btn.textContent = isExpanded ? expandedText : collapsedText;
                btn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            };

            syncState();

            btn.addEventListener('click', function () {
                target.classList.toggle('d-none');
                syncState();
            });
        });

        const searchEl = document.getElementById('tableSearch');
        if (searchEl) {
            searchEl.addEventListener('input', function () {
                lastSearchCaret = {
                    start: searchEl.selectionStart,
                    end: searchEl.selectionEnd,
                };
                if (searchTimer) clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    fetchTable(1);
                }, 700);
            });
        }

        const showHiddenToggle = document.getElementById('showHiddenRowsToggle');
        const showHiddenOnlyToggle = document.getElementById('showHiddenOnlyRowsToggle');
        const showHiddenInput = document.getElementById('show_hidden');
        const showHiddenOnlyInput = document.getElementById('show_hidden_only');

        if (showHiddenToggle) {
            showHiddenToggle.addEventListener('change', function () {
                if (showHiddenInput) showHiddenInput.value = showHiddenToggle.checked ? '1' : '0';
                if (!showHiddenToggle.checked && showHiddenOnlyToggle) {
                    showHiddenOnlyToggle.checked = false;
                    if (showHiddenOnlyInput) showHiddenOnlyInput.value = '0';
                }
                fetchTable(1);
            });
        }

        if (showHiddenOnlyToggle) {
            showHiddenOnlyToggle.addEventListener('change', function () {
                if (showHiddenOnlyInput) showHiddenOnlyInput.value = showHiddenOnlyToggle.checked ? '1' : '0';
                if (showHiddenOnlyToggle.checked && showHiddenToggle) {
                    showHiddenToggle.checked = true;
                    if (showHiddenInput) showHiddenInput.value = '1';
                }
                fetchTable(1);
            });
        }

        syncCurrentPageSelectionFromMap();
        updateBulkActionsUI();
        updateBulkDockPosition();
    }

    const tableLoadingOverlayId = 'reviewTableLoadingOverlay';

    function setTableLoading(isLoading) {
        if (!tableContainer) return;
        let overlay = document.getElementById(tableLoadingOverlayId);
        if (!overlay && isLoading) {
            overlay = document.createElement('div');
            overlay.id = tableLoadingOverlayId;
            overlay.style.position = 'absolute';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.background = 'rgba(255,255,255,0.8)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '10';
            overlay.innerHTML = '<div class="text-muted small">Loading rows...</div>';
            tableContainer.style.position = 'relative';
            tableContainer.appendChild(overlay);
            return;
        }

        if (!isLoading && overlay) {
            overlay.remove();
        }
    }

    async function fetchTable(page) {
        if (!filterForm || !tableContainer) return;
        currentPage = page || 1;

        const activeElement = document.activeElement;
        const shouldRestoreSearchFocus = !!(activeElement && activeElement.id === 'tableSearch');

        const fd = new FormData(filterForm);
        
        // Ensure hidden inputs are set before creating FormData
        const showHiddenToggle = document.getElementById('showHiddenRowsToggle');
        const showHiddenInput = document.getElementById('show_hidden');
        const showHiddenOnlyToggle = document.getElementById('showHiddenOnlyRowsToggle');
        const showHiddenOnlyInput = document.getElementById('show_hidden_only');
        
        if (showHiddenToggle && showHiddenInput) {
            fd.set('show_hidden', showHiddenToggle.checked ? '1' : '0');
        }
        if (showHiddenOnlyToggle && showHiddenOnlyInput) {
            fd.set('show_hidden_only', showHiddenOnlyToggle.checked ? '1' : '0');
        }
        
        const searchEl = document.getElementById('tableSearch');
        if (searchEl) {
            fd.set('q', searchEl.value || '');
        }
        fd.set('page', String(page || 1));

        const params = new URLSearchParams();
        fd.forEach(function (value, key) {
            if (typeof value === 'string') params.append(key, value);
        });

        setTableLoading(true);

        try {
            const url = '{{ route('cc.region.review') }}?' + params.toString();
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Failed to load rows');
            const data = await res.json();

            tableContainer.innerHTML = data.table_html || '<div class="text-muted small">No data.</div>';

            if (countTotal && data.counts) countTotal.textContent = Number(data.counts.total || 0).toLocaleString();
            if (countVisible && data.counts) countVisible.textContent = Number(data.counts.visible || 0).toLocaleString();
            if (countHidden && data.counts) countHidden.textContent = Number(data.counts.hidden || 0).toLocaleString();

            applyRowBindings();

            if (shouldRestoreSearchFocus) {
                const refreshedSearchEl = document.getElementById('tableSearch');
                if (refreshedSearchEl) {
                    refreshedSearchEl.focus({ preventScroll: true });
                    const valueLength = refreshedSearchEl.value.length;
                    const start = Math.max(0, Math.min(lastSearchCaret?.start ?? valueLength, valueLength));
                    const end = Math.max(start, Math.min(lastSearchCaret?.end ?? start, valueLength));
                    refreshedSearchEl.setSelectionRange(start, end);
                }
            }
        } catch (err) {
            showTopToast('Unable to load rows.', true);
        } finally {
            setTableLoading(false);
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            fetchTable(1);
        });

        const reportEl = filterForm.querySelector('select[name="report"]');
        if (reportEl) {
            reportEl.addEventListener('change', function () {
                selectedRows.clear();
                fetchTable(1);
            });
        }

    }

    if (tableContainer) {
        tableContainer.addEventListener('click', function (ev) {
            const pagerLink = ev.target.closest('.pagination a');
            if (pagerLink) {
                ev.preventDefault();
                window.location.href = pagerLink.href;
            }
        });
    }

    function showTopToast(message, isError = false) {
        const existing = document.getElementById('topToast');
        if (existing) existing.closest('[aria-live]')?.remove();

        const wrapper = document.createElement('div');
        wrapper.setAttribute('aria-live', 'polite');
        wrapper.setAttribute('aria-atomic', 'true');
        wrapper.style.zIndex = '2100';
        wrapper.style.position = 'fixed';
        wrapper.style.left = '50%';
        wrapper.style.transform = 'translateX(-50%)';
        wrapper.style.top = '0.75rem';

        const toast = document.createElement('div');
        toast.id = 'topToast';
        toast.className = 'toast align-items-center text-bg-light border shadow-sm' + (isError ? ' border-danger' : '');
        toast.role = 'alert';
        toast.ariaLive = 'assertive';
        toast.ariaAtomic = 'true';
        toast.style.minWidth = '360px';
        toast.style.maxWidth = '720px';

        const inner = document.createElement('div');
        inner.className = 'd-flex';

        const body = document.createElement('div');
        body.className = 'toast-body text-center w-100';
        body.textContent = message;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-close me-2 m-auto';
        btn.dataset.bsDismiss = 'toast';
        btn.ariaLabel = 'Close';
        btn.addEventListener('click', function () {
            wrapper.remove();
        });

        inner.appendChild(body);
        inner.appendChild(btn);
        toast.appendChild(inner);
        wrapper.appendChild(toast);
        document.body.appendChild(wrapper);

        try {
            new bootstrap.Toast(toast, { delay: 4000 }).show();
        } catch (e) {
            // ignore
        }
    }

    if (bulkForm) {
        document.addEventListener('click', function (ev) {
            const btn = ev.target.closest('.bulk-action-btn');
            if (!btn) return;
            ev.preventDefault();

            const action = btn.getAttribute('data-action') === 'unhide' ? 'unhide' : 'hide';
            const groups = getSelectedRowsByType();
            const targetRows = action === 'unhide' ? groups.hidden : groups.visible;

            if (!targetRows.length) {
                showTopToast(action === 'unhide' ? 'Select hidden rows to unhide.' : 'Select visible rows to hide.', true);
                return;
            }

            openBulkConfirm(action, targetRows);
        });
    }

    if (bulkActionConfirmSubmit) {
        bulkActionConfirmSubmit.addEventListener('click', async function () {
            if (!pendingBulkAction || !pendingRowIds.length) {
                if (bulkConfirmModal) bulkConfirmModal.hide();
                return;
            }

            const action = pendingBulkAction;
            const rowIds = pendingRowIds.slice();
            pendingBulkAction = null;
            pendingRowIds = [];

            if (bulkConfirmModal) bulkConfirmModal.hide();
            await submitBulkAction(action, rowIds);
        });
    }

    if (bulkClearSelectionBtn) {
        bulkClearSelectionBtn.addEventListener('click', function () {
            selectedRows.clear();
            syncCurrentPageSelectionFromMap();
            updateBulkActionsUI();
        });
    }

    window.addEventListener('scroll', updateBulkDockPosition, { passive: true });
    window.addEventListener('resize', function () {
        measureBulkDockAnchorTop();
        updateBulkDockPosition();
    });

    initConfirmModal();
    measureBulkDockAnchorTop();
    applyRowBindings();
});
</script>
@endpush
@endsection
