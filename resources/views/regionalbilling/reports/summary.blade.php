@extends('layouts.cc')

@section('title', 'Report Summary')

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
                        <p class="text-uppercase text-muted small mb-1">Regional Billing</p>
                        <h1 class="h4 mb-0">Reports</h1>
                        <p class="text-muted small mb-0">Region: {{ $region ?? '—' }} | RTOM: {{ strtoupper($rtom ?? '—') }}</p>
                    </div>
                </div>
                <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                    <a href="{{ route('rb.reports.history') }}" class="btn btn-outline-primary btn-sm">Browse past reports</a>
                    <p class="small text-muted mb-0">See every report we ran with call center users, their interactions, and the payouts that followed.</p>
                </div>

                @if(($reports ?? collect())->isNotEmpty())
                <div class="row g-3 mt-3 align-items-center">
                    <div class="col-md-8">
                        <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                            <label for="report" class="mb-0 text-muted small me-2" style="min-width: 180px;">Select report</label>
                            <select id="report" name="report" class="form-select form-select-sm rounded" onchange="this.form.submit()">
                                @foreach(($reports ?? collect()) as $candidate)
                                    @php
                                        $dm = $candidate->dataset_month;
                                        $label = ($dm && strlen($dm) === 6)
                                            ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report'
                                            : ('Report #' . $candidate->id);
                                    @endphp
                                    <option value="{{ $candidate->id }}" {{ (int) $candidate->id === (int) $report->id ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div class="col-md-4 text-md-end">
                        @if($anyAssigned ?? false)
                        <p class="small text-uppercase text-muted mb-0">Assigned rows ready</p>
                        @endif
                    </div>
                </div>
                @endif

                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <div class="card bg-light border-0 h-100 rounded-4">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Dataset month</p>
                                @php
                                    $s = $report->dataset_month ?? null;
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
                                <h2 class="h4 mb-0">{{ number_format($rows->total() ?? 0) }}</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    @if($errors->has('distribute'))
                        <div class="alert alert-warning">{{ $errors->first('distribute') }}</div>
                    @endif

                    @if(($callers ?? collect())->isEmpty())
                        <div class="alert alert-info">No active callers found under your account. Create callers first, then distribute rows.</div>
                    @elseif(($unassigned ?? 0) > 0)
                        <div class="card border-0 rounded-4 bg-white shadow-sm">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-3">Distribute rows to call center users</p>
                                <form method="post" action="{{ route('rb.reports.distribute', $report->id) }}" class="row g-2 align-items-center">
                                    @csrf
                                    <div class="col-md-6">
                                        <label class="small text-muted">Select users</label>
                                        <div class="mb-2 d-flex align-items-center gap-2">
                                            <input type="checkbox" id="rb-select-all-callers" />
                                            <label for="rb-select-all-callers" class="small mb-0">Select all</label>
                                            <div class="small text-muted ms-2">Distributable rows: <strong id="rb-distributable-count">{{ $unassigned }}</strong></div>
                                        </div>

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
                                            <input type="search" id="rb-user-search" class="form-control form-control-sm" placeholder="Search users (username or name)">
                                        </div>
                                        <div class="cc-user-scroll mt-1">
                                            @foreach($callers as $caller)
                                                @php
                                                    $pending = (int) ($pendingCounts[$caller->id] ?? 0);
                                                @endphp
                                                <div class="cc-user-row" data-user-id="{{ $caller->id }}">
                                                    <div class="cc-user-checkbox">
                                                        <input type="checkbox" class="form-check-input rb-caller-check" name="user_ids[]" id="rb-caller-{{ $caller->id }}" value="{{ $caller->id }}">
                                                    </div>
                                                    <div class="cc-user-meta">
                                                        <div class="cc-user-name">
                                                            <strong>{{ $caller->username }}</strong>
                                                            @if(!empty($caller->name))
                                                                &nbsp;&mdash;&nbsp;<span class="text-muted small">{{ $caller->name }}</span>
                                                            @endif
                                                        </div>
                                                        @if($pending > 0)
                                                        <div class="small text-muted">
                                                            <span>Pending (this report): <span class="badge bg-secondary text-white small">{{ $pending }}</span></span>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    <div class="cc-user-share">
                                                        <span class="badge bg-light text-dark rb-share-badge">0</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <!-- selector column left intentionally wide -->
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-sm w-100" {{ $unassigned <= 0 ? 'disabled' : '' }}>Distribute</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if($anyAssigned ?? false)
                    @php
                        $acceptedUserIds = collect($acceptedCounts ?? [])->filter(fn($count) => (int) $count > 0)->keys()->map(fn($id) => (int) $id)->values()->all();
                    @endphp
                    <div class="row g-4 mt-4">
                        <div class="col-12 col-lg-4">
                            <div class="card border-0 rounded-4 bg-white shadow-sm h-100 d-flex flex-column">
                                <div class="card-body flex-grow-1">
                                    <h3 class="h6 text-uppercase text-muted small mb-3">Rejected assignments</h3>
                                    @php $hasRejected = false; @endphp
                                    <ul class="list-unstyled mb-0">
                                        @foreach($callers as $caller)
                                            @php $count = (int) ($rejectedCounts[$caller->id] ?? 0); @endphp
                                            @if($count > 0)
                                                @php $hasRejected = true; @endphp
                                                <li class="d-flex justify-content-between align-items-center py-1">
                                                    <div class="small">
                                                        <strong>{{ $caller->username }}</strong>
                                                        @if(!empty($caller->name))
                                                            &nbsp;&mdash;&nbsp;<span class="text-muted">{{ $caller->name }}</span>
                                                        @endif
                                                    </div>
                                                    <span class="badge bg-warning text-dark small">{{ $count }}</span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                    @if(!$hasRejected)
                                    <p class="text-muted small mb-0">No rejected rows for this report.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card border-0 rounded-4 bg-white shadow-sm h-100 d-flex flex-column">
                                <div class="card-body flex-grow-1">
                                    <h6 class="small text-uppercase text-muted mb-2">Pending approvals</h6>
                                    <ul class="list-unstyled mb-0">
                                        @php $hasPending = false; @endphp
                                        @foreach($callers as $caller)
                                            @php $pending = (int) ($pendingCounts[$caller->id] ?? 0); @endphp
                                            @if($pending > 0 && ! in_array((int) $caller->id, $acceptedUserIds, true))
                                                @php $hasPending = true; @endphp
                                                <li class="d-flex justify-content-between align-items-center py-1">
                                                    <div class="small">
                                                        <strong>{{ $caller->username }}</strong>
                                                        @if(!empty($caller->name))
                                                            &nbsp;&mdash;&nbsp;<span class="text-muted">{{ $caller->name }}</span>
                                                        @endif
                                                    </div>
                                                    <span class="badge bg-secondary text-white small">{{ $pending }}</span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                    @if(!$hasPending)
                                    <p class="text-muted small mb-0">No pending rows for this report.</p>
                                    @endif
                                    <p class="small text-muted mt-2">Each pending user receives one bundled notice for all rows they have been given; if those rows stay untouched for three days you will see them return to this widget for another redistribution round.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card border-0 rounded-4 bg-white shadow-sm h-100 d-flex flex-column">
                                <div class="card-body flex-grow-1">
                                    <h3 class="h6 text-uppercase text-muted small mb-3">Approved assignments</h3>
                                    <ul class="list-unstyled mb-0">
                                        @php $hasApproved = false; @endphp
                                        @foreach($callers as $caller)
                                            @php $approved = (int) ($acceptedCounts[$caller->id] ?? 0); @endphp
                                            @if($approved > 0)
                                                @php $hasApproved = true; @endphp
                                                <li class="d-flex justify-content-between align-items-center py-1">
                                                    <div class="small">
                                                        <strong>{{ $caller->username }}</strong>
                                                        @if(!empty($caller->name))
                                                            &nbsp;&mdash;&nbsp;<span class="text-muted">{{ $caller->name }}</span>
                                                        @endif
                                                    </div>
                                                    <span class="badge bg-secondary text-white small">{{ $approved }}</span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                    @if(!$hasApproved)
                                    <p class="text-muted small mb-0">No approved rows for this report yet.</p>
                                    @endif
                                    <p class="small text-muted mt-2">Accepted users see these counts plus any newly assigned rows that land on their desk; they may accept immediately or reject without a reason, and each rejection simply raises the pending/accepted counts again for the remaining teams.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('rb-select-all-callers');
    const checkboxes = Array.from(document.querySelectorAll('.rb-caller-check'));
    const searchInput = document.getElementById('rb-user-search');
    const distributable = Number(@json((int) ($unassigned ?? 0)));
    const distributableCountEl = document.getElementById('rb-distributable-count');

    function updateShares() {
        const selected = checkboxes.filter(function (cb) {
            return cb.checked;
        });
        const count = selected.length;
        const badges = document.querySelectorAll('.rb-share-badge');
        if (count === 0 || distributable <= 0) {
            badges.forEach(function (b) {
                b.textContent = '0';
            });
            return;
        }

        const base = Math.floor(distributable / count);
        let rem = distributable % count;
        checkboxes.forEach(function (cb) {
            const row = cb.closest('.cc-user-row');
            const badge = row ? row.querySelector('.rb-share-badge') : null;
            if (!badge) return;
            if (!cb.checked) {
                badge.textContent = '0';
                return;
            }
            const add = rem > 0 ? 1 : 0;
            badge.textContent = String(base + add);
            if (rem > 0) rem -= 1;
        });
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateShares);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const checked = !!selectAll.checked;
            checkboxes.forEach(function (cb) {
                const row = cb.closest('.cc-user-row');
                if (row && row.style.display === 'none') return;
                cb.checked = checked;
            });
            updateShares();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = String(searchInput.value || '').trim().toLowerCase();
            checkboxes.forEach(function (cb) {
                const row = cb.closest('.cc-user-row');
                if (!row) return;
                const nameEl = row.querySelector('.cc-user-name');
                const text = (nameEl ? nameEl.textContent : '').toLowerCase();
                row.style.display = (q === '' || text.includes(q)) ? '' : 'none';
            });
        });
    }

    if (distributableCountEl) {
        distributableCountEl.textContent = String(distributable);
    }

    updateShares();
});
</script>
@endpush
@endsection