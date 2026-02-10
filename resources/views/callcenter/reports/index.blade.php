@php use Illuminate\Support\Str; @endphp

@extends('layouts.cc')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card shadow-sm" style="border-radius: 1rem;">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <p class="text-uppercase text-muted small mb-1">Call Center</p>
                        <h1 class="h4 mb-0">Reports</h1>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div id="cc-report-loader" class="cc-report-loader">
                            <div class="cc-report-loader__inner">
                                <span class="cc-report-loader__spinner"></span>
                                <div>
                                    <p class="mb-0 fw-semibold">Preparing Excel download...</p>
                                    <p class="small text-muted mb-0">Do not close the page until the file starts downloading.</p>
                                </div>
                            </div>
                        </div>
                        @if($selectedReport)
                        @php
                        $recallDisabled = $acceptedAssignments->isNotEmpty() || !empty($hasInteractions);
                        @endphp
                        <a id="cc-report-download" href="{{ route('cc.reports.download', $selectedReport->id) }}" class="btn btn-outline-secondary rounded-pill px-4" download data-loader-off="1">Download Excel</a>
                        @if(($anyAssigned ?? false) && !($recallDisabled))
                        <!-- Recall modal trigger -->
                        <button type="button" class="btn btn-outline-warning rounded-pill px-3" id="cc-recall-open-btn"
                            title="Undo every assignment for this report"
                            data-bs-toggle="modal" data-bs-target="#ccRecallModal">
                            Undo all assignments
                        </button>

                        <!-- Recall confirmation modal -->
                        <div class="modal fade" id="ccRecallModal" tabindex="-1" aria-labelledby="ccRecallModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="ccRecallModalLabel">Undo all assignments</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>This permanently removes every assignment (and any related interactions) for this report as long as no rows were accepted and no call activity exists. Continue?</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form id="cc-recall-form" method="post" action="{{ route('cc.reports.recall', $selectedReport->id) }}" class="m-0">
                                            @csrf
                                            <button type="submit" class="btn btn-warning">Undo assignments</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endunless
                        @endif
                    </div>
                </div>
                <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                    <a href="{{ route('cc.reports.history') }}" class="btn btn-outline-primary btn-sm">Browse past reports</a>
                    <p class="small text-muted mb-0">See every report we ran with call center users, their interactions, and the payouts that followed.</p>
                </div>
                <div class="row g-3 mt-3 align-items-center">
                    <div class="col-md-8">
                        <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                            <label for="report" class="mb-0 text-muted small me-2" style="min-width: 180px;">Select report</label>
                            <select id="report" name="report" class="form-select form-select-sm rounded" onchange="this.form.submit()">
                                @foreach($reports->take(2) as $report)
                                @php
                                $dm = $report->dataset_month;
                                $label = ($dm && strlen($dm) === 6)
                                ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report'
                                : sprintf('Report #%s', $report->id);
                                @endphp
                                <option value="{{ $report->id }}" {{ optional($selectedReport)->id === $report->id ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div class="col-md-4 text-md-end">
                        @if($selectedReport && ($anyAssigned ?? false))
                        <p class="small text-uppercase text-muted mb-0">Assigned rows ready</p>
                        @endif
                    </div>
                </div>

                {{-- Status is shown as a toast at the top; avoid duplicate inline alert --}}
                @if($errors->has('reassign'))
                <div class="alert alert-warning mt-4">{{ $errors->first('reassign') }}</div>
                @endif
                @if($selectedReport)
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100 rounded-4">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Dataset month</p>
                                @php
                                $s = $selectedReport->dataset_month ?? null;
                                $sLabel = ($s && strlen($s) === 6) ? substr($s,0,4) . '/' . substr($s,4,2) . ' report' : ($s ?: '—');
                                @endphp
                                <h2 class="h4 mb-0">{{ $sLabel }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100 rounded-4">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Call Center rows captured</p>
                                @if(session('user.assignment') && Str::startsWith(session('user.assignment'), 'supervisor_'))
                                <h2 class="h4 mb-0">{{ number_format(count($allowedRowIds ?? [])) }}</h2>
                                @else
                                <h2 class="h4 mb-0">{{ number_format($selectedReport->row_count) }}</h2>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    @if(! ($allAssigned ?? false) && (($distributableRows ?? 0) > 0))
                    <div class="col-12">
                        <div class="card border-0 rounded-4 bg-white shadow-sm">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-3">Distribute rows to call center users</p>
                                <form method="post" action="{{ (session('user.assignment') && Str::startsWith(session('user.assignment'), 'supervisor_')) ? route('cc.reports.distribute_supervisor', $selectedReport->id) : route('cc.reports.distribute', $selectedReport->id) }}" class="row g-2 align-items-center">
                                    @csrf
                                    <div class="col-md-6">
                                        <label class="small text-muted">Select users</label>
                                        <div class="mb-2 d-flex align-items-center gap-2">
                                            <input type="checkbox" id="cc-select-all-users" /> <label for="cc-select-all-users" class="small mb-0">Select all</label>
                                            <div class="small text-muted ms-2">Distributable rows: <strong id="cc-distributable-count">{{ $distributableRows ?? 0 }}</strong></div>
                                        </div>
                                        @if(Str::startsWith(session('user.assignment') ?? '', 'supervisor_'))
                                            <div class="small text-muted mt-1">* = callers you own (supervisor-created)</div>
                                        @endif

                                        <style nonce="{{ $cspNonce ?? '' }}">
                                            .cc-user-scroll {
                                                border: 1px solid #e9ecef;
                                                border-radius: 0.375rem;
                                                overflow-y: auto;
                                                max-height: 260px;
                                            }

                                            .cc-user-row {
                                                padding: 0.5rem 0.75rem;
                                                display: flex;
                                                align-items: center;
                                                gap: 0.75rem;
                                                border-bottom: 1px solid rgba(0, 0, 0, 0.03);
                                            }

                                            .cc-user-row:last-child {
                                                border-bottom: none;
                                            }

                                            .cc-user-checkbox {
                                                flex: 0 0 28px;
                                            }

                                            .cc-user-meta {
                                                flex: 1 1 auto;
                                                min-width: 0;
                                            }

                                            .cc-user-share {
                                                flex: 0 0 96px;
                                                text-align: right;
                                            }

                                            .cc-user-name {
                                                white-space: nowrap;
                                                overflow: hidden;
                                                text-overflow: ellipsis;
                                            }
                                        </style>

                                        <div class="mb-2">
                                            <input type="search" id="cc-user-search" class="form-control form-control-sm" placeholder="Search users (username or name)">
                                        </div>
                                        <div class="cc-user-scroll mt-1 cc-user-scroll">
                                            @foreach($ccUsers as $user)
                                            @php
                                            $pending = (int) ($pendingCounts[$user->id] ?? 0);
                                            $prevPending = (int) (($prevPendingCounts[$user->id] ?? 0));
                                            @endphp
                                            <div class="cc-user-row" data-user-id="{{ $user->id }}">
                                                <div class="cc-user-checkbox">
                                                    <input type="checkbox" class="form-check-input cc-user-checkbox-input" name="user_ids[]" id="cc-user-{{ $user->id }}" value="{{ $user->id }}">
                                                </div>
                                                <div class="cc-user-meta">
                                                    <div class="cc-user-name">
                                                        <strong>{{ $user->username }}@if(isset($currentSupervisorId) && $user->supervisor === $currentSupervisorId) *@endif</strong>
                                                        @if(is_null($user->name))
                                                            &nbsp;&mdash;&nbsp;<span class="text-muted small">new user</span>
                                                        @elseif(trim((string) ($user->name ?? '')) !== '')
                                                            &nbsp;&mdash;&nbsp;<span class="text-muted small">{{ $user->name }}</span>
                                                        @endif
                                                    </div>
                                                    @if($pending > 0 || $prevPending > 0)
                                                    <div class="small text-muted d-flex gap-3 flex-wrap">
                                                        @if($pending > 0)
                                                        <span>Pending (this report): <span class="badge bg-secondary text-white small">{{ $pending }}</span></span>
                                                        @endif
                                                        @if($prevPending > 0)
                                                        <span>Prev report pending: <span class="badge bg-warning text-dark small">{{ $prevPending }}</span></span>
                                                        @endif
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="cc-user-share">
                                                    <span class="badge bg-light text-dark cc-share-badge">0</span>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <!-- selector column left intentionally wide -->
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">Distribute</button>
                                    </div>
                                </form>
                                <script nonce="{{ $cspNonce ?? '' }}">
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const distributable = Number(@json((int)($distributableRows ?? 0)));
                                        const checkboxes = Array.from(document.querySelectorAll('.cc-user-checkbox-input'));
                                        const selectAll = document.getElementById('cc-select-all-users');
                                        const searchInput = document.getElementById('cc-user-search');
                                        const distributableCountEl = document.getElementById('cc-distributable-count');
                                        // removed per-user manual input; server will calculate allocation

                                        function updateShares() {
                                            const selected = checkboxes.filter(cb => cb.checked);
                                            const count = selected.length;
                                            const badges = document.querySelectorAll('.cc-share-badge');
                                            if (count === 0 || distributable <= 0) {
                                                badges.forEach(b => b.textContent = '0');
                                                return;
                                            }
                                            const base = Math.floor(distributable / count);
                                            let rem = distributable % count;
                                            // assign shares in DOM order to keep predictable distribution
                                            checkboxes.forEach((cb, i) => {
                                                const row = cb.closest('.cc-user-row');
                                                const badge = row?.querySelector('.cc-share-badge');
                                                if (!badge) return;
                                                if (!cb.checked) {
                                                    badge.textContent = '0';
                                                    return;
                                                }
                                                const add = rem > 0 ? 1 : 0;
                                                const share = base + add;
                                                badge.textContent = String(share);
                                                if (rem > 0) rem -= 1;
                                            });
                                            // client shows per-user badge only; server will receive user_ids[] and compute distribution
                                        }

                                        // wire checkbox changes
                                        checkboxes.forEach(cb => cb.addEventListener('change', updateShares));

                                        // search filtering (case-insensitive match against displayed name)
                                        searchInput?.addEventListener('input', function() {
                                            const q = this.value.trim().toLowerCase();
                                            checkboxes.forEach(cb => {
                                                const row = cb.closest('.cc-user-row');
                                                const text = row.querySelector('.cc-user-name')?.textContent.toLowerCase() || '';
                                                row.style.display = q === '' || text.includes(q) ? '' : 'none';
                                            });
                                        });

                                        // select-all toggles visible rows only
                                        selectAll?.addEventListener('change', function() {
                                            const v = !!this.checked;
                                            checkboxes.forEach(cb => {
                                                const row = cb.closest('.cc-user-row');
                                                if (row && row.style.display === 'none') return; // skip hidden rows
                                                cb.checked = v;
                                            });
                                            updateShares();
                                        });

                                        // initial calculation
                                        if (distributableCountEl) distributableCountEl.textContent = String(distributable);
                                        updateShares();
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                @if($anyAssigned ?? false)
                <div class="row g-4 mt-4">
                    <div class="col-12 col-lg-4">
                        <div class="card border-0 rounded-4 bg-white shadow-sm h-100 d-flex flex-column">
                            <div class="card-body flex-grow-1">
                                <h3 class="h6 text-uppercase text-muted small mb-3">Rejected assignments</h3>
                                @if($rejectedSummary->isNotEmpty())
                                <div class="alert alert-warning py-2 px-3 mb-3 small text-dark">
                                    <p class="mb-1 fw-semibold text-uppercase small">Latest rejections</p>
                                    <ul class="mb-0 ps-3">
                                        @foreach($rejectedSummary as $summary)
                                        <li>
                                            {{ $summary['label'] }} rejected {{ $summary['count'] }} rows{{ $summary['last_rejected_at'] ? ' (' . $summary['last_rejected_at'] . ')' : '' }}.
                                            @if($summary['reason'])
                                            <span class="text-muted">Reason: {{ $summary['reason'] }}</span>
                                            @endif
                                        </li>
                                        @endforeach
                                    </ul>
                                    <p class="mb-0 text-muted small">You can reassign these rows to other users (new, pending, or already accepted) below; if they stay unaccepted for 3 days, they reappear in this list for another round, and once you reissue a rejection the completed record vanishes from this card.</p>
                                </div>
                                @endif
                                @if($rejectedAssignments->isEmpty())
                                <p class="text-muted small mb-0">No rejected rows for this report.</p>
                                @else
                                <form method="post" action="{{ route('cc.reports.reassign', $selectedReport->id) }}" class="cc-reassign-form">
                                    @csrf
                                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                                        <label class="small text-muted mb-0">Select users to redistribute</label>
                                        <div style="min-width:260px;max-width:320px;max-height:200px;overflow:auto;">
                                            @php $targetUsers = $nonRejectingUsers->isNotEmpty() ? $nonRejectingUsers : $ccUsers; @endphp
                                            @foreach($targetUsers as $user)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="user_ids[]" value="{{ $user->id }}" id="reassign-user-{{ $user->id }}">
                                                <label class="form-check-label small" for="reassign-user-{{ $user->id }}">{{ $user->username }} — {{ $user->name }}</label>
                                            </div>
                                            @endforeach
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">Reassign selected</button>
                                        <button type="button" id="cc-reassign-all" class="btn btn-sm btn-outline-primary">Reassign ALL rejected</button>
                                        <button type="submit" name="action" value="pool" class="btn btn-sm btn-secondary">Return selected to pool</button>
                                    </div>
                                    <p class="small text-muted mb-3">Use the checkboxes to reassign to new users or to users who already have pending/accepted rows. Accepted users will see this as additional rows they can pick up or reject without a reason, which again increases the pool for others.</p>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col" class="text-nowrap"><input type="checkbox" id="cc-reject-select-all" checked></th>
                                                    <th scope="col">Row</th>
                                                    <th scope="col">Phone</th>
                                                    <th scope="col">Rejected by</th>
                                                    <th scope="col">Note</th>
                                                    <th scope="col">Rejected</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($rejectedAssignments as $assignment)
                                                <tr>
                                                    <td class="text-nowrap"><input type="checkbox" class="cc-reject-checkbox" name="assignment_ids[]" value="{{ $assignment->id }}" checked></td>
                                                    <td>#{{ $assignment->row->id ?? $assignment->id }}</td>
                                                    <td>{{ optional($assignment->row)->phone ?? '—' }}</td>
                                                    <td>{{ optional($assignment->rejectedBy)->username ?? 'Unknown' }}</td>
                                                    <td>{{ $assignment->rejection_note ?? 'No note' }}</td>
                                                    <td>{{ optional($assignment->rejected_at)->diffForHumans() ?? '—' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card border-0 rounded-4 bg-white shadow-sm h-100 d-flex flex-column">
                            <div class="card-body flex-grow-1">
                                <h6 class="small text-uppercase text-muted mb-2">Pending approvals</h6>
                                <ul class="list-unstyled mb-0">
                                    @php $acceptedUserIds = $acceptedAssignments->pluck('assigned_user_id')->unique()->filter()->values()->all(); @endphp
                                    @foreach($ccUsers as $user)
                                    @php $pending = (int) ($pendingCounts[$user->id] ?? 0); @endphp
                                    @if($pending > 0 && ! in_array($user->id, $acceptedUserIds))
                                    <li class="d-flex justify-content-between align-items-center py-1">
                                        <div class="small">
                                            @php
                                                $owned = isset($currentSupervisorId) && $user->supervisor === $currentSupervisorId;
                                            @endphp
                                            <strong>{{ $user->username }}@if($owned) *@endif</strong>
                                            @if(is_null($user->name))
                                                &nbsp;&mdash;&nbsp;<span class="text-muted">new user</span>
                                            @elseif(trim((string) ($user->name ?? '')) !== '')
                                                &nbsp;&mdash;&nbsp;<span class="text-muted">{{ $user->name }}</span>
                                            @endif
                                        </div>
                                        <span class="badge bg-secondary text-white small">{{ $pending }}</span>
                                    </li>
                                    @endif
                                    @endforeach
                                </ul>
                                <p class="small text-muted mt-2">Each pending user receives one bundled notice for all rows they have been given; if those rows stay untouched for three days you will see them return to this widget for another redistribution round.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card border-0 rounded-4 bg-white shadow-sm h-100 d-flex flex-column">
                            <div class="card-body flex-grow-1">
                                <h3 class="h6 text-uppercase text-muted small mb-3">Approved assignments</h3>
                                @if($acceptedAssignments->isEmpty())
                                <p class="text-muted small mb-0">No approved rows for this report yet.</p>
                                @else
                                @php
                                $acceptedGrouped = $acceptedAssignments->groupBy(fn($a) => $a->assigned_user_id);
                                @endphp
                                <ul class="list-unstyled mb-0">
                                    @foreach($acceptedGrouped as $uid => $group)
                                    @php $agent = optional($group->first()->agent); @endphp
                                    <li class="d-flex justify-content-between align-items-center py-1 cc-approved-user" data-user-id="{{ $uid }}" style="cursor:pointer;">
                                        <div class="small">{{ $agent->username ?? 'User '.$uid }} — {{ $agent->name ?? '' }}</div>
                                        <span class="badge bg-secondary text-white small">{{ $group->count() }}</span>
                                    </li>
                                    @endforeach
                                </ul>
                                @endif
                                <p class="small text-muted mt-2">Accepted users see these counts plus any newly assigned rows that land on their desk; they may accept immediately or reject without a reason, and each rejection simply raises the pending/accepted counts again for the remaining teams.</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @else
                <div class="alert alert-info mt-3">No call center reports have been recorded yet.</div>
                @endif
            </div>
        </div>
        <!-- Recent reports section removed per request -->
        <script nonce="{{ $cspNonce ?? '' }}">
            document.addEventListener('DOMContentLoaded', () => {
                const loader = document.getElementById('cc-report-loader');
                const download = document.getElementById('cc-report-download');
                const selectAll = document.getElementById('cc-reject-select-all');
                const reassignAllBtn = document.getElementById('cc-reassign-all');
                const rejectedCheckboxes = Array.from(document.querySelectorAll('.cc-reject-checkbox'));

                download?.addEventListener('click', () => {
                    loader?.classList.add('cc-report-loader--visible');
                    const hideLoader = () => loader?.classList.remove('cc-report-loader--visible');
                    window.addEventListener('focus', hideLoader, {
                        once: true
                    });
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) hideLoader();
                    });
                    setTimeout(hideLoader, 5000);
                });

                selectAll?.addEventListener('change', (event) => {
                    rejectedCheckboxes.forEach((checkbox) => {
                        checkbox.checked = event.target.checked;
                    });
                });

                reassignAllBtn?.addEventListener('click', () => {
                    rejectedCheckboxes.forEach((checkbox) => checkbox.checked = true);
                    const form = document.querySelector('.cc-reassign-form');
                    form?.submit();
                });

                // Distribution UI reverted to server-side multi-select; no client-side distribution logic is required here.

                const approvedUserEls = document.querySelectorAll('.cc-approved-user');
                if (!approvedUserEls.length) return;

                const assignmentRowModal = document.getElementById('ccAssignmentRowModal');
                const assignmentList = document.getElementById('ccAssignmentRowList');
                const assignmentDetailFields = document.getElementById('ccAssignmentDetailFields');
                const callForm = document.getElementById('ccAssignmentCallForm');
                const outcomeEl = document.getElementById('ccCallOutcome');
                const paymentWrap = document.getElementById('ccPaymentExpectedWrap');
                const paymentInput = document.getElementById('ccPaymentExpected');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                let assignmentModalInstance = null;
                if (assignmentRowModal && window.bootstrap) assignmentModalInstance = new bootstrap.Modal(assignmentRowModal, {
                    keyboard: true
                });

                function showModalOrFallback(node, bsInstance) {
                    if (!node) return;
                    if (bsInstance && typeof bsInstance.show === 'function') {
                        node.style.display = '';
                        bsInstance.show();
                        return;
                    }
                    node.style.display = 'block';
                    node.classList.add('cc-fallback-modal');
                    if (!document.getElementById('cc-fallback-backdrop')) {
                        const backdrop = document.createElement('div');
                        backdrop.id = 'cc-fallback-backdrop';
                        backdrop.className = 'cc-fallback-backdrop';
                        backdrop.addEventListener('click', () => hideModalOrFallback(node));
                        document.body.appendChild(backdrop);
                    }
                }

                function hideModalOrFallback(node) {
                    if (!node) return;
                    node.style.display = 'none';
                    node.classList.remove('cc-fallback-modal');
                    const back = document.getElementById('cc-fallback-backdrop');
                    if (back) back.remove();
                }

                document.addEventListener('click', function(ev) {
                    const btn = ev.target.closest('[data-bs-dismiss="modal"]');
                    if (!btn) return;
                    const modal = btn.closest('.modal');
                    if (modal) hideModalOrFallback(modal);
                });

                async function loadUserAccepted(userId) {
                    const params = new URLSearchParams();
                    const selectedReportId = @json($selectedReport->id ?? null);
                    if (selectedReportId) {
                        params.set('report', String(selectedReportId));
                    }
                    const res = await fetch(`/cc/assignments/${userId}/rows?${params.toString()}`, {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) return null;
                    return await res.json();
                }

                async function loadAssignmentDetails(assignmentId) {
                    if (!assignmentId) return null;
                    const res = await fetch(`/cc/assignments/${assignmentId}/details`, {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) return null;
                    return await res.json();
                }

                function renderDetails(data) {
                    if (!assignmentDetailFields) return;
                    // reset form disabled state and visual wrapper
                    const outcome = document.getElementById('ccCallOutcome');
                    const note = document.getElementById('ccCallNote');
                    const pay = document.getElementById('ccPaymentExpected');
                    const saveBtn = document.getElementById('ccSaveCallBtn');
                    const startBtn = document.getElementById('ccStartCallBtn');
                    const wrapper = document.getElementById('ccCallFormWrapper');
                    if (outcome) outcome.disabled = true;
                    if (note) note.disabled = true;
                    if (pay) pay.disabled = true;
                    if (saveBtn) {
                        saveBtn.disabled = true;
                        saveBtn.classList.add('d-none');
                    }
                    if (startBtn) startBtn.disabled = false;
                    if (wrapper) wrapper.classList.add('cc-disabled');

                    if (!data) {
                        assignmentDetailFields.innerHTML = '<div class="text-muted small">Details unavailable.</div>';
                        document.getElementById('ccSelectedName').textContent = '';
                        document.getElementById('ccSelectedAmounts').textContent = '';
                        return;
                    }

                    // populate left detail pane
                    assignmentDetailFields.innerHTML = `
                                <div class="mb-2"><strong>Phone:</strong> ${data.phone ?? '—'}</div>
                                <div class="mb-2"><strong>Address:</strong> ${data.address ?? '—'}</div>
                                <div class="mb-2"><strong>RTOM:</strong> ${data.rtom ?? '—'}</div>
                                <div class="mb-2"><strong>Customer ref:</strong> ${data.customer_ref ?? '—'}</div>
                                <div class="mb-2"><strong>Account #:</strong> ${data.account_num ?? '—'}</div>
                                <div class="mb-2"><strong>Sales person:</strong> ${data.sales_person ?? '—'}</div>
                                <div class="mb-2"><strong>Sales channel:</strong> ${data.sales_channel ?? '—'}</div>
                                <div class="mb-2"><strong>Full address:</strong> ${data.full_address ?? '—'}</div>
                            `;

                    // interactions history (descending)
                    if (Array.isArray(data.interactions) && data.interactions.length) {
                        let hist = '<div class="mt-3"><div class="small text-muted mb-2">Recent interactions</div><div class="list-group list-group-flush">';
                        data.interactions.forEach(i => {
                            hist += `<div class="list-group-item small">`;
                            hist += `<div class="fw-semibold">${i.agent_name ? i.agent_name : ('Agent #'+(i.agent_id||'—'))} <span class="text-muted small">${i.created_at ? ' — '+i.created_at : ''}</span></div>`;
                            hist += `<div class="text-muted">${i.outcome ? i.outcome : '—'}`;
                            if (i.account_number) hist += ` — Acc: ${i.account_number}`;
                            hist += `</div>`;
                            if (i.note) hist += `<div class="mt-1">${i.note}</div>`;
                            hist += `</div>`;
                        });
                        hist += '</div></div>';
                        assignmentDetailFields.innerHTML += hist;
                    }

                    // set selected name and amounts in right column
                    const selName = document.getElementById('ccSelectedName');
                    const selAmt = document.getElementById('ccSelectedAmounts');
                    if (selName) selName.textContent = data.address_name || data.name || '';
                    if (selAmt) selAmt.textContent = `Arrears: ${data.arrears ?? '—'} — Bill: ${data.bill ?? '—'}` + (data.call_count ? ` — Calls since assignment: ${data.call_count}` : '');

                    // wire Start Call button to enable form
                    const startBtnEl = document.getElementById('ccStartCallBtn');
                    if (startBtnEl) {
                        startBtnEl.onclick = () => {
                            if (outcome) outcome.disabled = false;
                            if (note) note.disabled = false;
                            if (pay) pay.disabled = false;
                            if (saveBtn) {
                                saveBtn.disabled = false;
                                saveBtn.classList.remove('d-none');
                            }
                            if (wrapper) wrapper.classList.remove('cc-disabled');
                            startBtnEl.disabled = true;
                        };
                    }
                }

                approvedUserEls.forEach(el => {
                    el.addEventListener('click', async function() {
                        const userId = this.dataset.userId;
                        const payload = await loadUserAccepted(userId);
                        if (!assignmentList) return;
                        assignmentList.innerHTML = '';
                        if (!payload || !payload.rows || !payload.rows.length) {
                            assignmentList.innerHTML = '<div class="small text-muted p-3">No accepted rows.</div>';
                        } else {
                            payload.rows.forEach(async r => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
                                btn.dataset.assignmentId = r.assignment_id;
                                btn.innerHTML = `<div><strong>${r.address_name ?? '—'}</strong><div class="small text-muted">Arrears: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'}</div></div><div class="text-muted small">#${r.row_id}</div>`;
                                btn.addEventListener('click', async () => {
                                    const assignmentId = btn.dataset.assignmentId;
                                    document.getElementById('ccCallAssignmentId').value = assignmentId;
                                    const details = await loadAssignmentDetails(assignmentId);
                                    renderDetails(details);
                                });
                                assignmentList.appendChild(btn);
                            });
                        }
                        showModalOrFallback(assignmentRowModal, assignmentModalInstance);
                    });
                });

                outcomeEl?.addEventListener('change', function() {
                    const v = this.value;
                    if (v === 'agreed to pay within 3 days' || v === 'agreed to pay within 7 days') {
                        paymentWrap.style.display = 'block';
                        const days = v.includes('3') ? 3 : 7;
                        const d = new Date();
                        d.setDate(d.getDate() + days);
                        paymentInput.value = d.toISOString().slice(0, 10);
                    } else {
                        paymentWrap.style.display = 'none';
                        paymentInput.value = '';
                    }
                });

                callForm?.addEventListener('submit', async function(ev) {
                    ev.preventDefault();
                    const aid = document.getElementById('ccCallAssignmentId').value;
                    if (!aid) {
                        document.getElementById('ccCallStatus').textContent = 'Select a row first.';
                        return;
                    }
                    const formData = new FormData(this);
                    try {
                        const res = await fetch(`/cc/assignments/${aid}/interactions`, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });
                        if (!res.ok) throw new Error('failed');
                        document.getElementById('ccCallStatus').textContent = 'Saved.';
                        // clear the call form so values don't persist after save
                        try {
                            callForm.reset();
                            if (paymentWrap) paymentWrap.style.display = 'none';
                            if (paymentInput) paymentInput.value = '';
                            if (outcomeEl) outcomeEl.selectedIndex = 0;
                            const saveBtn = document.getElementById('ccSaveCallBtn');
                            if (saveBtn) {
                                saveBtn.disabled = true;
                                saveBtn.classList.add('d-none');
                            }
                            const wrapper = document.getElementById('ccCallFormWrapper');
                            if (wrapper) wrapper.classList.add('cc-disabled');
                            const startBtn = document.getElementById('ccStartCallBtn');
                            if (startBtn) startBtn.disabled = false;
                        } catch (e) {
                            console.error('Error clearing form after save', e);
                        }
                        // refresh details to show new interaction on top
                        const updated = await loadAssignmentDetails(aid);
                        renderDetails(updated);
                    } catch (err) {
                        document.getElementById('ccCallStatus').textContent = 'Save failed.';
                    }
                });
            }
            });
        </script>
        <div class="modal fade" id="ccAssignmentRowModal" tabindex="-1" aria-labelledby="ccAssignmentRowModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ccAssignmentRowModalLabel">Accepted rows</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row gy-4">
                            <div class="col-lg-6">
                                <p class="small text-uppercase text-muted mb-2">Assigned rows for this agent</p>
                                <div id="ccAssignmentRowList" class="list-group shadow-sm" style="max-height: 380px; overflow:auto;"></div>
                                <div id="ccAssignmentDetailPane" class="mt-3">
                                    <p class="small text-uppercase text-muted mb-1">Selected row</p>
                                    <div id="ccAssignmentDetailFields" class="border rounded-3 p-3 bg-light small text-muted">Select a row to see the details.</div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <div id="ccSelectedName" class="fw-semibold">&nbsp;</div>
                                        <div id="ccSelectedAmounts" class="small text-muted">&nbsp;</div>
                                    </div>
                                    <div>
                                        <button type="button" id="ccStartCallBtn" class="btn btn-sm btn-outline-primary">Start call</button>
                                    </div>
                                </div>
                                <div id="ccCallFormWrapper" class="card-body bg-white rounded-3">
                                    <form id="ccAssignmentCallForm" class="row g-3" data-loader-off="1">
                                        <input type="hidden" name="assignment_id" id="ccCallAssignmentId">
                                        <div class="col-12">
                                            <label class="form-label small">Outcome</label>
                                            <select name="outcome" id="ccCallOutcome" class="form-select form-select-sm" disabled>
                                                <option value="number invalid">number invalid</option>
                                                <option value="user not authorized">user not authorized</option>
                                                <option value="agreed to pay within 3 days">agreed to pay within 3 days</option>
                                                <option value="agreed to pay within 7 days">agreed to pay within 7 days</option>
                                                <option value="Not answered">Not answered</option>
                                            </select>
                                        </div>
                                        <div class="col-12" id="ccPaymentExpectedWrap" style="display:none;">
                                            <label class="form-label small">Payment expected at</label>
                                            <input type="date" name="payment_expected_at" id="ccPaymentExpected" class="form-control form-control-sm" disabled>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small">Note (optional)</label>
                                            <textarea name="note" id="ccCallNote" class="form-control form-control-sm" rows="3" disabled></textarea>
                                        </div>
                                        <div class="col-12 d-flex gap-2">
                                            <button type="submit" id="ccSaveCallBtn" class="btn btn-primary btn-sm d-none" disabled>Save call</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                        </div>
                                        <div class="col-12">
                                            <div id="ccCallStatus" class="small text-muted"></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endsection

        @push('styles')
            <style nonce="{{ $cspNonce ?? '' }}">
            #cc-user-checkboxes .list-group-item {
                border: 1px solid #e7e7f0;
                border-radius: 0.85rem;
                padding: 0.8rem;
                margin-bottom: 0.4rem;
                background: #fff;
            }

            #cc-user-checkboxes .list-group-item:last-child {
                margin-bottom: 0;
            }

            .cc-user-share {
                min-width: 70px;
                text-align: right;
            }

            .process-upload-card.process-upload-card--transparent .card-body {
                padding: 3rem;
            }

            #cc-report-loader {
                display: none;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem 0.75rem;
                border-radius: 999px;
                background: rgba(248, 249, 252, 0.9);
            }

            #cc-report-loader.cc-report-loader--visible {
                display: inline-flex;
            }

            .cc-report-loader__inner {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .cc-report-loader__spinner {
                width: 22px;
                height: 22px;
                border-radius: 50%;
                border: 2px solid rgba(22, 22, 22, 0.1);
                border-top-color: #0d6efd;
                animation: cc-report-spinner 0.8s linear infinite;
            }

            @keyframes cc-report-spinner {
                from {
                    transform: rotate(0deg);
                }

                to {
                    transform: rotate(360deg);
                }
            }

            /* fallback modal styles when Bootstrap CSS isn't available */
            .cc-fallback-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 1070;
            }

            .cc-fallback-modal {
                position: fixed !important;
                inset: 50% auto auto 50% !important;
                transform: translate(-50%, -50%) !important;
                z-index: 1080 !important;
                max-width: 940px !important;
                width: calc(100% - 40px) !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                border-radius: 0.5rem;
                background: #fff;
            }

            #ccCallFormWrapper.cc-disabled {
                opacity: 0.6;
                pointer-events: none;
            }
        </style>
        <script src="/js/cc-user-select2.js"></script>
        @endpush