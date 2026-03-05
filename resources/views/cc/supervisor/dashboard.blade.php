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
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center — RTO: {{ $rtom }}</p>
                        <h1 class="process-upload-title mb-0">Supervisor Dashboard</h1>
                        @if($latestReport)
                            <p class="text-muted small mb-0">Latest report: {{ $latestReport->dataset_month ? substr($latestReport->dataset_month, 0, 4) . '/' . substr($latestReport->dataset_month, 4, 2) : 'Report #' . $latestReport->id }}</p>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-group btn-group-sm" role="group" aria-label="View mode" id="supervisorViewMode">
                            <button type="button" class="btn btn-outline-secondary active" data-mode="latest">Latest report</button>
                            <button type="button" class="btn btn-outline-secondary" data-mode="all-time">All reports</button>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4" data-mode="latest">
                    <div class="col-md-2">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Total Assignments</div>
                            <div class="h4 mb-0">{{ $latestTotal }}</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Assigned</div>
                            <div class="h4 mb-0">{{ $latestAssigned }}</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Unassigned</div>
                            <div class="h4 mb-0">{{ $latestUnassigned }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Paid (count)</div>
                            <div class="h4 mb-0">{{ $latestPaidCount }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Paid Amount</div>
                            <div class="h4 mb-0">{{ number_format($latestPaidAmount, 2) }}</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4" data-mode="all-time" style="display: none;">
                    <div class="col-md-2">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Total Assignments</div>
                            <div class="h4 mb-0">{{ $allTimeTotal }}</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Assigned</div>
                            <div class="h4 mb-0">{{ $allTimeAssigned }}</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Unassigned</div>
                            <div class="h4 mb-0">{{ $allTimeUnassigned }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Paid (count)</div>
                            <div class="h4 mb-0">{{ $allTimePaidCount }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card process-upload-card process-upload-card--transparent shadow-sm p-3">
                            <div class="small text-muted">Paid Amount</div>
                            <div class="h4 mb-0">{{ number_format($allTimePaidAmount, 2) }}</div>
                        </div>
                    </div>
                </div>

                <div data-mode="latest">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Caller Breakdown (Latest Report)</h6>
                        <input type="search" class="form-control form-control-sm" id="latestCallerSearch" placeholder="Search callers..." style="width: 300px;">
                    </div>
                    <div class="table-responsive cc-table-container">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Caller</th>
                                    <th>Supervisor</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th class="text-end">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody id="latestCallerTableBody">
                                @forelse($latestCallerBreakdown as $c)
                                <tr class="caller-row">
                                    <td>{{ $c['agent'] }}</td>
                                    <td>{{ $c['supervisor'] }}</td>
                                    <td>{{ $c['total'] }}</td>
                                    <td>{{ $c['paid'] }}</td>
                                    <td class="text-end">{{ number_format($c['paid_amount'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-muted">No data available for the latest report.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div data-mode="all-time" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Caller Breakdown (All-Time)</h6>
                        <input type="search" class="form-control form-control-sm" id="allTimeCallerSearch" placeholder="Search callers..." style="width: 300px;">
                    </div>
                    <div class="table-responsive cc-table-container">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Caller</th>
                                    <th>Supervisor</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th class="text-end">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody id="allTimeCallerTableBody">
                                @forelse($allTimeCallerBreakdown as $c)
                                <tr class="caller-row">
                                    <td>{{ $c['agent'] }}</td>
                                    <td>{{ $c['supervisor'] }}</td>
                                    <td>{{ $c['total'] }}</td>
                                    <td>{{ $c['paid'] }}</td>
                                    <td class="text-end">{{ number_format($c['paid_amount'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-muted">No data available for all-time.</td></tr>
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
// View mode toggle: switch between latest report and all-time
function setSupervisorViewMode(mode) {
    document.querySelectorAll('[data-mode]:not(button)').forEach(function (el) {
        if (el.hasAttribute('data-mode')) {
            el.style.display = el.getAttribute('data-mode') === mode ? '' : 'none';
        }
    });
    // update button active state
    document.querySelectorAll('#supervisorViewMode [data-mode]').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-mode') === mode);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // View mode toggle
    document.querySelectorAll('#supervisorViewMode [data-mode]').forEach(function (b) {
        b.addEventListener('click', function () {
            setSupervisorViewMode(this.getAttribute('data-mode'));
        });
    });
    // default to latest
    setSupervisorViewMode('latest');

    // Caller search functionality
    function setupSearch(searchInputId, tableBodyId) {
        const searchInput = document.getElementById(searchInputId);
        const tableBody = document.getElementById(tableBodyId);
        const rows = tableBody.querySelectorAll('.caller-row');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            rows.forEach(row => {
                const callerName = row.cells[0].textContent.toLowerCase();
                const shouldShow = callerName.includes(searchTerm);
                row.style.display = shouldShow ? '' : 'none';
            });
        });
    }

    setupSearch('latestCallerSearch', 'latestCallerTableBody');
    setupSearch('allTimeCallerSearch', 'allTimeCallerTableBody');
});
</script>
@endpush

@endsection