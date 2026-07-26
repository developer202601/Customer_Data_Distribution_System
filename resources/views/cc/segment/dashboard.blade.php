@extends('layouts.cc')

@section('title', 'Segment Dashboard')

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
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center — Segment: {{ $segmentLabel }}</p>
                        <h1 class="process-upload-title mb-0">Segment Dashboard</h1>
                        @if($latestReport)
                            <p class="text-muted small mb-0">Latest report:
                                {{ $latestReport->dataset_month && strlen($latestReport->dataset_month) === 6
                                    ? substr($latestReport->dataset_month, 0, 4) . '/' . substr($latestReport->dataset_month, 4, 2)
                                    : 'Report #' . $latestReport->id }}
                            </p>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-group btn-group-sm" role="group" id="segmentViewMode">
                            <button type="button" class="btn btn-outline-secondary active" data-mode="latest">Latest report</button>
                            <button type="button" class="btn btn-outline-secondary" data-mode="all-time">All reports</button>
                        </div>
                        <a href="{{ route('cc.segment.callers') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">Manage Callers</a>
                    </div>
                </div>

                {{-- Latest report stats --}}
                <div data-mode="latest">
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Total Assignments</div>
                                <div class="h4 mb-0">{{ number_format($latestTotal) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Assigned</div>
                                <div class="h4 mb-0">{{ number_format($latestAssigned) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Unassigned</div>
                                <div class="h4 mb-0">{{ number_format($latestUnassigned) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Paid (count)</div>
                                <div class="h4 mb-0">{{ number_format($latestPaidCount) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Caller Breakdown (Latest Report)</h6>
                        <input type="search" class="form-control form-control-sm" id="latestCallerSearch"
                            placeholder="Search callers..." style="width: 300px;">
                    </div>
                    <div class="table-responsive cc-table-container">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Caller</th>
                                    <th>Total</th>
                                    <th>Assigned</th>
                                    <th>Paid</th>
                                    <th class="text-end">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody id="latestCallerTableBody">
                                @forelse($latestCallerBreakdown as $r)
                                    <tr class="caller-row" data-caller="{{ $r['agent'] }}">
                                        <td>{{ $r['agent'] }}</td>
                                        <td>{{ $r['total'] }}</td>
                                        <td>{{ $r['assigned'] }}</td>
                                        <td>{{ $r['paid'] }}</td>
                                        <td class="text-end">{{ number_format($r['paid_amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">No data for the latest report.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- All-time stats --}}
                <div data-mode="all-time" style="display:none;">
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Total Assignments</div>
                                <div class="h4 mb-0">{{ number_format($allTimeTotal) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Assigned</div>
                                <div class="h4 mb-0">{{ number_format($allTimeAssigned) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Unassigned</div>
                                <div class="h4 mb-0">{{ number_format($allTimeUnassigned) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                                <div class="small text-muted">Paid (count)</div>
                                <div class="h4 mb-0">{{ number_format($allTimePaidCount) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Caller Breakdown (All-Time)</h6>
                        <input type="search" class="form-control form-control-sm" id="allTimeCallerSearch"
                            placeholder="Search callers..." style="width: 300px;">
                    </div>
                    <div class="table-responsive cc-table-container">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Caller</th>
                                    <th>Total</th>
                                    <th>Assigned</th>
                                    <th>Paid</th>
                                    <th class="text-end">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody id="allTimeCallerTableBody">
                                @forelse($allTimeCallerBreakdown as $r)
                                    <tr class="caller-row" data-caller="{{ $r['agent'] }}">
                                        <td>{{ $r['agent'] }}</td>
                                        <td>{{ $r['total'] }}</td>
                                        <td>{{ $r['assigned'] }}</td>
                                        <td>{{ $r['paid'] }}</td>
                                        <td class="text-end">{{ number_format($r['paid_amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">No all-time data available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    function setMode(mode) {
        document.querySelectorAll('[data-mode]:not(button)').forEach(el => {
            el.style.display = el.getAttribute('data-mode') === mode ? '' : 'none';
        });
        document.querySelectorAll('#segmentViewMode [data-mode]').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-mode') === mode);
        });
    }

    document.querySelectorAll('#segmentViewMode [data-mode]').forEach(b => {
        b.addEventListener('click', () => setMode(b.getAttribute('data-mode')));
    });
    setMode('latest');

    function setupSearch(inputId, bodyId) {
        const input = document.getElementById(inputId);
        const body  = document.getElementById(bodyId);
        if (!input || !body) return;
        input.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            body.querySelectorAll('.caller-row').forEach(row => {
                row.style.display = row.dataset.caller.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    setupSearch('latestCallerSearch', 'latestCallerTableBody');
    setupSearch('allTimeCallerSearch', 'allTimeCallerTableBody');
});
</script>
@endpush
@endsection
