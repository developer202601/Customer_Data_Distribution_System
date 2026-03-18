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

                <form method="get" id="reviewFiltersForm" class="row g-2 align-items-end mb-3">
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
                    </div>

                    <form id="bulkRowsForm" method="post" action="{{ route('cc.region.review.hide_rows', $selectedReport->id) }}">
                        @csrf
                        <div id="reviewTableContainer">
                            @include('cc.region._report_review_table', ['selectedReport' => $selectedReport, 'rows' => $rows, 'showHidden' => $showHidden, 'showHiddenOnly' => $showHiddenOnly, 'isLocked' => !empty($reviewRecord?->reviewed_at)])
                        </div>
                    </form>
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

    /* Floating bulk action buttons */
    .floating-bulk-actions {
        position: fixed;
        bottom: 1rem;
        right: 1rem;
        z-index: 1200;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.95);
        padding: 0.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
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

    @media (max-width: 768px) {
        .floating-bulk-actions {
            bottom: 0.75rem;
            right: 0.75rem;
            left: 0.75rem;
            justify-content: center;
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
    const hideRowsUrl = '{{ route('cc.region.review.hide_rows', $selectedReport->id) }}';
    let searchTimer = null;
    let currentPage = 1;

    function applyRowBindings() {
        const selectAll = document.getElementById('selectAllRows');
        const checks = Array.from(document.querySelectorAll('.row-check'));

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checks.forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
            });
        }

        document.querySelectorAll('#reviewRowsTable .review-row').forEach(function (row) {
            row.addEventListener('click', function (ev) {
                if (ev.target.closest('input.row-check')) return;
                if (ev.target.closest('.row-details-btn')) return;
                const cb = row.querySelector('input.row-check');
                if (!cb) return;
                cb.checked = !cb.checked;
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
                if (searchTimer) clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    fetchTable(1);
                }, 250);
            });
        }

        const showHiddenToggle = document.getElementById('showHiddenRowsToggle');
        const showHiddenOnlyToggle = document.getElementById('showHiddenOnlyRowsToggle');
        const showHiddenInput = document.getElementById('show_hidden');
        const showHiddenOnlyInput = document.getElementById('show_hidden_only');

        if (showHiddenToggle && showHiddenInput) {
            showHiddenToggle.addEventListener('change', function () {
                showHiddenInput.value = showHiddenToggle.checked ? '1' : '0';
                if (!showHiddenToggle.checked && showHiddenOnlyToggle && showHiddenOnlyInput) {
                    showHiddenOnlyToggle.checked = false;
                    showHiddenOnlyInput.value = '0';
                }
                fetchTable(1);
            });
        }

        if (showHiddenOnlyToggle && showHiddenOnlyInput) {
            showHiddenOnlyToggle.addEventListener('change', function () {
                showHiddenOnlyInput.value = showHiddenOnlyToggle.checked ? '1' : '0';
                if (showHiddenOnlyToggle.checked && showHiddenToggle && showHiddenInput) {
                    showHiddenToggle.checked = true;
                    showHiddenInput.value = '1';
                }
                fetchTable(1);
            });
        }
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

        const fd = new FormData(filterForm);
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
                fetchTable(1);
            });
        }

    }

    if (tableContainer) {
        tableContainer.addEventListener('click', function (ev) {
            const pagerLink = ev.target.closest('.pagination a');
            if (pagerLink) {
                ev.preventDefault();
                try {
                    const u = new URL(pagerLink.href);
                    const nextPage = Number(u.searchParams.get('page') || '1');
                    fetchTable(nextPage);
                } catch (e) {
                    fetchTable(1);
                }
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
        bulkForm.addEventListener('click', async function (ev) {
            const btn = ev.target.closest('.bulk-action-btn');
            if (!btn) return;
            ev.preventDefault();

            const action = btn.getAttribute('data-action');
            const actionInput = bulkForm.querySelector('input[name="action"]');
            if (actionInput) actionInput.value = action || 'hide';

            const submitButtons = Array.from(bulkForm.querySelectorAll('button, input[type="submit"]'));
            submitButtons.forEach(function (b) { b.disabled = true; });

            const formData = new FormData(bulkForm);
            const searchEl = document.getElementById('tableSearch');
            if (searchEl) formData.set('q', searchEl.value || '');

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
                    await fetchTable(currentPage);
                }
            } catch (err) {
                showTopToast('Unable to update rows.', true);
            } finally {
                submitButtons.forEach(function (b) { b.disabled = false; });
            }
        });
    }

    applyRowBindings();
});
</script>
@endpush
@endsection
