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
    let searchTimer = null;

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

    async function fetchTable(page) {
        if (!filterForm || !tableContainer) return;

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

        tableContainer.innerHTML = '<div class="text-muted small">Loading rows...</div>';

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
            tableContainer.innerHTML = '<div class="text-danger small">Unable to load rows.</div>';
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

    if (bulkForm) {
        bulkForm.addEventListener('submit', function () {
            const searchEl = document.getElementById('tableSearch');
            const existing = bulkForm.querySelector('input[name="q"]');
            if (!existing) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'q';
                hidden.value = searchEl ? (searchEl.value || '') : '';
                bulkForm.appendChild(hidden);
            } else {
                existing.value = searchEl ? (searchEl.value || '') : '';
            }
        });
    }

    applyRowBindings();
});
</script>
@endpush
@endsection
