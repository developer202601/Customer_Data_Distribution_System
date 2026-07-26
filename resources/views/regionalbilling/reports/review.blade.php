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
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Region: {{ $region }}</p>
                        <h1 class="process-upload-title mb-0">Review Report Rows</h1>
                        <p class="text-muted small mb-0">Hide unwanted rows before they are distributed to lower levels.</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2" data-loader-off="1">
                        <a href="{{ route('rb.region.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-4">Back to Dashboard</a>
                        @if(!empty($selectedReport) && $selectedReport->id)
                        <a href="{{ route('rb.reports.download', $selectedReport->id) }}" class="btn btn-outline-success rounded-pill px-4">Download Excel</a>
                        @endif
                    </div>
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
                        <form method="post" action="{{ route('rb.reports.preference') }}" class="d-flex align-items-center gap-2 ms-auto">
                            @csrf
                            <input type="hidden" name="enable_regional_review" value="{{ !empty($reviewOptIn) ? '0' : '1' }}">
                            <span class="badge {{ !empty($reviewOptIn) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ !empty($reviewOptIn) ? 'Enabled' : 'Disabled' }}</span>
                            <button type="submit" class="btn btn-sm {{ !empty($reviewOptIn) ? 'btn-outline-danger' : 'btn-outline-success' }} rounded-pill px-3">
                                {{ !empty($reviewOptIn) ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </div>
                </div>

                @if(session('status') || $errors->any() || session('rb.reports.include_preview_count'))
                    <div class="mb-3">
                        @if(session('status'))
                            <div class="alert alert-success mb-2">
                                {{ session('status') }}
                                @if(session('rb.reports.include_preview_count'))
                                    <div class="small text-muted mt-1">
                                        Inclusion file matched {{ number_format((int) session('rb.reports.include_preview_count')) }} row(s).
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger mb-0">
                                <div class="fw-semibold mb-1">Unable to process the uploaded file.</div>
                                <ul class="mb-0 ps-3">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

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

                        <div id="reviewTableContainer" data-loader-off="1">
                            @include('regionalbilling.reports._review_table', [
                                'selectedReport' => $selectedReport,
                                'rows' => $rows,
                                'showHidden' => $showHidden,
                                'showHiddenOnly' => $showHiddenOnly,
                                'isLocked' => !empty($reviewRecord?->reviewed_at),
                                'search' => $search,
                                'rtomsWithDetails' => $rtomsWithDetails ?? [],
                                'canUnlockReview' => $canUnlockReview ?? false,
                                'passedRtomNames' => $passedRtomNames ?? []
                            ])
                        </div>
                        <div id="rbToastContainer" class="rb-toast-container"></div>
                        <style nonce="{{ $cspNonce ?? '' }}">
                            .rb-toast-container {
                                position: fixed;
                                right: 1rem;
                                bottom: 1rem;
                                z-index: 1080;
                                width: auto;
                                max-width: 360px;
                                pointer-events: none;
                            }
                            .rb-toast-container .toast {
                                pointer-events: auto;
                                margin-bottom: 0.5rem;
                            }
                        </style>

                        {{-- Exclude File Modal --}}
                        <div class="modal fade" id="excludeFileModal" tabindex="-1" aria-labelledby="excludeFileModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-semibold" id="excludeFileModalLabel">Upload Exclude File</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form id="excludeFileForm" method="post" action="{{ route('rb.reports.exclude_file', $selectedReport->id) }}" enctype="multipart/form-data" data-loader-off="1">
                                        @csrf
                                        <div class="modal-body">
                                            <p class="text-muted small mb-3">
                                                Upload an <strong>.xlsx</strong> workbook with an <code>ACCOUNT_NUM</code> column.
                                                Any matching rows will be hidden from the review set.
                                            </p>
                                            <div class="mb-3">
                                                <label for="excludeFileInput" class="form-label small fw-semibold">Select file</label>
                                                <input
                                                    type="file"
                                                    id="excludeFileInput"
                                                    name="exclude_file"
                                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                                    class="form-control"
                                                    required
                                                />
                                            </div>
                                            <div id="excludeFileModalError" class="alert alert-danger d-none mb-0 small"></div>
                                            <div id="excludeFileModalSuccess" class="alert alert-success d-none mb-0 small"></div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" id="excludeFileSubmitBtn" class="btn btn-danger rounded-pill px-4">
                                                <span id="excludeFileBtnText">Submit</span>
                                                <span id="excludeFileSpinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Include File Modal --}}
                        <div class="modal fade" id="includeFileModal" tabindex="-1" aria-labelledby="includeFileModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-semibold" id="includeFileModalLabel">Upload Inclusion File</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form id="includeFileForm" method="post" action="{{ route('rb.reports.include_file', $selectedReport->id) }}" enctype="multipart/form-data" data-loader-off="1">
                                        @csrf
                                        <div class="modal-body">
                                            <p class="text-muted small mb-3">
                                                Upload an <strong>.xlsx</strong> workbook with a <code>CUSTOMER_REF</code>, <code>ACCOUNT_NUM</code>, or <code>PRODUCT_LABEL</code> column.
                                                Only matching rows will remain visible — everything else will be hidden.
                                            </p>
                                            <div class="mb-3">
                                                <label for="includeFileInput" class="form-label small fw-semibold">Select file</label>
                                                <input
                                                    type="file"
                                                    id="includeFileInput"
                                                    name="include_file"
                                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                                    class="form-control"
                                                    required
                                                />
                                            </div>
                                            <div id="includeFileModalError" class="alert alert-danger d-none mb-0 small"></div>
                                            <div id="includeFileModalSuccess" class="alert alert-success d-none mb-0 small"></div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" id="includeFileSubmitBtn" class="btn btn-success rounded-pill px-4">
                                                <span id="includeFileBtnText">Submit</span>
                                                <span id="includeFileSpinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    </form>
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

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('reviewFiltersForm');
    const bulkForm = document.getElementById('bulkRowsForm');
    const tableContainer = document.getElementById('reviewTableContainer');
    const countTotal = document.getElementById('countTotalRows');
    const countVisible = document.getElementById('countVisibleRows');
    const countHidden = document.getElementById('countHiddenRows');
    const bulkHideBtn = document.getElementById('bulkHideBtn');
    const bulkUnhideBtn = document.getElementById('bulkUnhideBtn');
    const bulkClearSelectionBtn = document.getElementById('bulkClearSelectionBtn');
    const bulkActionsDock = document.getElementById('bulkActionsDock');
    const bulkActionsSelectionHint = document.getElementById('bulkActionsSelectionHint');
    const bulkActionsMixedHint = document.getElementById('bulkActionsMixedHint');
    const bulkActionsCountBadge = document.getElementById('bulkActionsCountBadge');
    const selectedRows = new Map();
    let currentPage = 1;

    function applyBindings() {
        const selectAll = document.getElementById('selectAllRows');
        const checks = Array.from(document.querySelectorAll('.row-check'));
        const isLocked = bulkActionsDock?.getAttribute('data-locked') === '1';

        if (selectAll) {
            selectAll.disabled = isLocked;
            selectAll.addEventListener('change', function () {
                if (isLocked) return;
                checks.forEach(function (cb) {
                    cb.checked = selectAll.checked;
                    const row = cb.closest('.review-row');
                    const rowId = row?.getAttribute('data-row-id');
                    const visibility = row?.getAttribute('data-row-visibility') || 'visible';
                    if (!rowId) return;
                    if (cb.checked) selectedRows.set(rowId, { id: rowId, visibility: visibility });
                    else selectedRows.delete(rowId);
                });
                updateBulkUi();
            });
        }

        checks.forEach(function (cb) {
            cb.disabled = isLocked;
            cb.addEventListener('change', function () {
                if (isLocked) {
                    cb.checked = false;
                    return;
                }
                const row = cb.closest('.review-row');
                const rowId = row?.getAttribute('data-row-id');
                const visibility = row?.getAttribute('data-row-visibility') || 'visible';
                if (!rowId) return;
                if (cb.checked) selectedRows.set(rowId, { id: rowId, visibility: visibility });
                else selectedRows.delete(rowId);
                updateBulkUi();
            });
        });

        document.querySelectorAll('#reviewRowsTable .row-details-btn').forEach(function (btn) {
            const targetId = btn.getAttribute('data-target-row-id');
            const target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;
            btn.addEventListener('click', function () {
                target.classList.toggle('d-none');
            });
        });
    }

    function showToast(message, isError = false) {
        const toastContainer = document.getElementById('rbToastContainer');
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-light border shadow-sm' + (isError ? ' border-danger' : '');
        toast.role = 'alert';
        toast.ariaLive = 'assertive';
        toast.ariaAtomic = 'true';

        const inner = document.createElement('div');
        inner.className = 'd-flex';

        const body = document.createElement('div');
        body.className = 'toast-body text-center w-100';
        body.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close me-2 m-auto';
        closeBtn.dataset.bsDismiss = 'toast';
        closeBtn.ariaLabel = 'Close';
        closeBtn.addEventListener('click', function () {
            toast.remove();
        });

        inner.appendChild(body);
        inner.appendChild(closeBtn);
        toast.appendChild(inner);
        toastContainer.appendChild(toast);

        if (window.bootstrap && typeof bootstrap.Toast === 'function') {
            new bootstrap.Toast(toast, { delay: 4000 }).show();
        }
    }

    function updateBulkUi() {
        const isLocked = bulkActionsDock?.getAttribute('data-locked') === '1';
        const selected = Array.from(selectedRows.values());
        const visible = selected.filter(function (x) { return x.visibility === 'visible'; });
        const hidden = selected.filter(function (x) { return x.visibility === 'hidden'; });

        if (isLocked) {
            if (bulkActionsSelectionHint) bulkActionsSelectionHint.textContent = 'Review is locked. Row visibility cannot be changed.';
            if (bulkActionsMixedHint) bulkActionsMixedHint.classList.add('d-none');
            if (bulkHideBtn) bulkHideBtn.classList.add('d-none');
            if (bulkUnhideBtn) bulkUnhideBtn.classList.add('d-none');
            if (bulkClearSelectionBtn) bulkClearSelectionBtn.classList.add('d-none');
            if (bulkActionsCountBadge) bulkActionsCountBadge.classList.add('d-none');
            return;
        }

        if (bulkActionsSelectionHint) bulkActionsSelectionHint.textContent = selected.length ? ('Selected rows: ' + selected.length) : 'Select rows to manage visibility.';
        if (bulkActionsCountBadge) {
            bulkActionsCountBadge.classList.toggle('d-none', !selected.length);
            bulkActionsCountBadge.textContent = selected.length ? ('Visible: ' + visible.length + ' | Hidden: ' + hidden.length) : '';
        }
        if (bulkHideBtn) bulkHideBtn.classList.toggle('d-none', !visible.length);
        if (bulkUnhideBtn) bulkUnhideBtn.classList.toggle('d-none', !hidden.length);
        if (bulkClearSelectionBtn) bulkClearSelectionBtn.classList.toggle('d-none', !selected.length);
    }

    async function fetchTable(page) {
        if (!filterForm || !tableContainer) return;
        currentPage = page || 1;
        const fd = new FormData(filterForm);
        const searchEl = document.getElementById('tableSearch');
        if (searchEl) fd.set('q', searchEl.value || '');
        fd.set('page', String(currentPage));
        const params = new URLSearchParams();
        fd.forEach(function (value, key) { if (typeof value === 'string') params.append(key, value); });
        const res = await fetch('{{ route('rb.reports') }}?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        if (!res.ok) return;
        const data = await res.json();
        tableContainer.innerHTML = data.table_html || '';
        if (countTotal && data.counts) countTotal.textContent = Number(data.counts.total || 0).toLocaleString();
        if (countVisible && data.counts) countVisible.textContent = Number(data.counts.visible || 0).toLocaleString();
        if (countHidden && data.counts) countHidden.textContent = Number(data.counts.hidden || 0).toLocaleString();
        if (bulkActionsDock && typeof data.is_locked !== 'undefined') {
            bulkActionsDock.dataset.locked = data.is_locked ? '1' : '0';
        }
        selectedRows.clear();
        const selectAll = document.getElementById('selectAllRows');
        if (selectAll) selectAll.checked = false;
        applyBindings();
        updateBulkUi();
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (ev) { ev.preventDefault(); fetchTable(1); });
        const reportEl = filterForm.querySelector('select[name="report"]');
        if (reportEl) reportEl.addEventListener('change', function () { fetchTable(1); });
    }

    if (tableContainer) {
        tableContainer.addEventListener('click', function (ev) {
            const pagerLink = ev.target.closest('.pagination a');
            if (!pagerLink) return;
            ev.preventDefault();
            window.location.href = pagerLink.href;
        });
    }

    if (bulkClearSelectionBtn) {
        bulkClearSelectionBtn.addEventListener('click', function () {
            selectedRows.clear();
            document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = false; });
            const selectAll = document.getElementById('selectAllRows');
            if (selectAll) selectAll.checked = false;
            updateBulkUi();
        });
    }

    async function postBulkAction(action) {
        if (!bulkForm) return;
        const target = Array.from(selectedRows.values()).filter(function (x) { return x.visibility === (action === 'hide' ? 'visible' : 'hidden'); });
        if (!target.length) return;

        const isLocked = bulkActionsDock?.getAttribute('data-locked') === '1';
        if (isLocked) {
            showToast('Review is locked. Unlock it to change row visibility.', true);
            return;
        }

        const bulkActionUrl = bulkForm.getAttribute('action');
        if (!bulkActionUrl) return;

        // Build FormData from the selectedRows Map (not from DOM checkboxes, which may not
        // reflect the full cross-page selection tracked in the Map).
        const rowIds = target.map(function (x) { return String(x.id); });
        const fd = new FormData();
        // Include the CSRF token from the form
        const csrfInput = bulkForm.querySelector('input[name="_token"]');
        if (csrfInput) fd.append('_token', csrfInput.value);
        fd.append('action', action);
        rowIds.forEach(function (id) { fd.append('row_ids[]', id); });

        console.log('Bulk action submitting', action, bulkActionUrl, rowIds);

        const res = await fetch(bulkActionUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        });
        const errorData = await res.json().catch(() => null);
        if (!res.ok) {
            const message = errorData?.message || 'Unable to update rows.';
            showToast(message, true);
            console.error('Bulk action failed', res.status, JSON.stringify(errorData, null, 2));
            return;
        }
        console.log('Bulk action succeeded', action, rowIds);
        fetchTable(currentPage);
    }

    if (bulkHideBtn) bulkHideBtn.addEventListener('click', function () { postBulkAction('hide'); });
    if (bulkUnhideBtn) bulkUnhideBtn.addEventListener('click', function () { postBulkAction('unhide'); });

    // -------------------------------------------------------------------------
    // Exclude / Include file modal AJAX submissions
    // -------------------------------------------------------------------------
    function setupFileModal(opts) {
        // opts: { formId, errorId, successId, submitBtnId, btnTextId, spinnerId, fileInputId, modalId }
        const form        = document.getElementById(opts.formId);
        const errorEl     = document.getElementById(opts.errorId);
        const successEl   = document.getElementById(opts.successId);
        const submitBtn   = document.getElementById(opts.submitBtnId);
        const btnText     = document.getElementById(opts.btnTextId);
        const spinner     = document.getElementById(opts.spinnerId);
        const modalEl     = document.getElementById(opts.modalId);
        if (!form || !modalEl) return;

        // Reset state whenever the modal opens
        modalEl.addEventListener('show.bs.modal', function () {
            form.reset();
            if (errorEl)   { errorEl.textContent = '';   errorEl.classList.add('d-none'); }
            if (successEl) { successEl.textContent = ''; successEl.classList.add('d-none'); }
            if (submitBtn) submitBtn.disabled = false;
            if (btnText)   btnText.textContent = 'Submit';
            if (spinner)   spinner.classList.add('d-none');
        });

        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();

            const fileInput = document.getElementById(opts.fileInputId);
            if (!fileInput || !fileInput.files.length) {
                if (errorEl) { errorEl.textContent = 'Please select a file.'; errorEl.classList.remove('d-none'); }
                return;
            }

            // Show spinner, disable submit
            if (submitBtn) submitBtn.disabled = true;
            if (btnText)   btnText.textContent = 'Uploading…';
            if (spinner)   spinner.classList.remove('d-none');
            if (errorEl)   { errorEl.textContent = '';   errorEl.classList.add('d-none'); }
            if (successEl) { successEl.textContent = ''; successEl.classList.add('d-none'); }

            function restoreBtn() {
                if (submitBtn) submitBtn.disabled = false;
                if (btnText)   btnText.textContent = 'Submit';
                if (spinner)   spinner.classList.add('d-none');
            }

            let res, data;
            try {
                const fd = new FormData(form);
                res = await fetch(form.getAttribute('action'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd
                });
                data = await res.json().catch(() => null);
            } catch (err) {
                restoreBtn();
                if (errorEl) { errorEl.textContent = 'Network error. Please check your connection and try again.'; errorEl.classList.remove('d-none'); }
                return;
            }

            restoreBtn();

            if (!res.ok) {
                const msg = data?.message
                    || (data?.errors ? Object.values(data.errors).flat().join(' ') : null)
                    || 'Upload failed. Please try again.';
                if (errorEl) { errorEl.textContent = msg; errorEl.classList.remove('d-none'); }
                return;
            }

            // Success — close modal immediately, then toast + refresh table
            const msg = data?.message || 'File processed successfully.';
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            // Wait for the modal to fully close before refreshing the table.
            // Running fetchTable during Bootstrap's close animation can interrupt
            // the backdrop removal, leaving the overlay stuck on screen.
            modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                // Safety: ensure body is fully cleaned up
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
                showToast(msg);
                fetchTable(currentPage);
            });

            bsModal.hide();
        });
    }

    setupFileModal({
        formId:      'excludeFileForm',
        errorId:     'excludeFileModalError',
        successId:   'excludeFileModalSuccess',
        submitBtnId: 'excludeFileSubmitBtn',
        btnTextId:   'excludeFileBtnText',
        spinnerId:   'excludeFileSpinner',
        fileInputId: 'excludeFileInput',
        modalId:     'excludeFileModal',
    });

    setupFileModal({
        formId:      'includeFileForm',
        errorId:     'includeFileModalError',
        successId:   'includeFileModalSuccess',
        submitBtnId: 'includeFileSubmitBtn',
        btnTextId:   'includeFileBtnText',
        spinnerId:   'includeFileSpinner',
        fileInputId: 'includeFileInput',
        modalId:     'includeFileModal',
    });

    document.addEventListener('change', function (ev) {
        if (ev.target?.id === 'showHiddenRowsToggle') {
            const input = document.getElementById('show_hidden');
            if (input) input.value = ev.target.checked ? '1' : '0';
            fetchTable(1);
        }
        if (ev.target?.id === 'showHiddenOnlyRowsToggle') {
            const input = document.getElementById('show_hidden_only');
            if (input) input.value = ev.target.checked ? '1' : '0';
            if (ev.target.checked) {
                const h = document.getElementById('showHiddenRowsToggle');
                const hi = document.getElementById('show_hidden');
                if (h) h.checked = true;
                if (hi) hi.value = '1';
            }
            fetchTable(1);
        }
    });

    let searchTimer = null;
    document.addEventListener('input', function (ev) {
        if (ev.target?.id !== 'tableSearch') return;
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { fetchTable(1); }, 600);
    });

    applyBindings();
    updateBulkUi();
});
</script>
@endpush
@endsection
