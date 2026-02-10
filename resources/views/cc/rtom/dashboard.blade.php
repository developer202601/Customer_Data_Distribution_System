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
                        <p class="text-uppercase text-muted mb-1">Call Center — RTOM: {{ $rtom }}</p>
                        <h1 class="process-upload-title mb-0">RTOM Dashboard</h1>
                        @if($latestReport)
                            <p class="text-muted small mb-0">Latest report: {{ $latestReport->dataset_month ? substr($latestReport->dataset_month, 0, 4) . '/' . substr($latestReport->dataset_month, 4, 2) : 'Report #' . $latestReport->id }}</p>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-group btn-group-sm" role="group" aria-label="View mode" id="rtomViewMode">
                            <button type="button" class="btn btn-outline-secondary active" data-mode="latest">Latest report</button>
                            <button type="button" class="btn btn-outline-secondary" data-mode="all-time">All reports</button>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('cc.region.assign.index') }}" class="btn btn-outline-success rounded-pill px-4">Assign Supervisors</a>
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
                        <h6 class="mb-0">Supervisor Breakdown (Latest Report)</h6>
                        <input type="search" class="form-control form-control-sm" id="latestSupervisorSearch" placeholder="Search supervisors..." style="width: 300px;">
                    </div>
                    <div class="table-responsive cc-table-container">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Supervisor</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th class="text-end">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody id="latestSupervisorTableBody">
                                @forelse($latestSupervisorBreakdown as $s)
                                <tr class="supervisor-row" data-supervisor="{{ $s['supervisor'] }}" data-callers="{{ json_encode($s['caller_profits']) }}" style="cursor: pointer;">
                                    <td>{{ $s['supervisor'] }}</td>
                                    <td>{{ $s['total'] }}</td>
                                    <td>{{ $s['paid'] }}</td>
                                    <td class="text-end">{{ number_format($s['paid_amount'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted">No supervisors found for the latest report.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div data-mode="all-time" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Supervisor Breakdown (All-Time)</h6>
                        <input type="search" class="form-control form-control-sm" id="allTimeSupervisorSearch" placeholder="Search supervisors..." style="width: 300px;">
                    </div>
                    <div class="table-responsive cc-table-container">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Supervisor</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th class="text-end">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody id="allTimeSupervisorTableBody">
                                @forelse($allTimeSupervisorBreakdown as $s)
                                <tr class="supervisor-row" data-supervisor="{{ $s['supervisor'] }}" data-callers="{{ json_encode($s['caller_profits']) }}" style="cursor: pointer;">
                                    <td>{{ $s['supervisor'] }}</td>
                                    <td>{{ $s['total'] }}</td>
                                    <td>{{ $s['paid'] }}</td>
                                    <td class="text-end">{{ number_format($s['paid_amount'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted">No supervisors found for all-time.</td></tr>
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
let callerProfitChart = null; // Global variable to hold the chart instance

// View mode toggle: switch between latest report and all-time
function setRtomViewMode(mode) {
    document.querySelectorAll('[data-mode]:not(button)').forEach(function (el) {
        if (el.hasAttribute('data-mode')) {
            el.style.display = el.getAttribute('data-mode') === mode ? '' : 'none';
        }
    });
    // update button active state
    document.querySelectorAll('#rtomViewMode [data-mode]').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-mode') === mode);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // View mode toggle
    document.querySelectorAll('#rtomViewMode [data-mode]').forEach(function (b) {
        b.addEventListener('click', function () {
            setRtomViewMode(this.getAttribute('data-mode'));
        });
    });
    // default to latest
    setRtomViewMode('latest');

    // Supervisor search functionality
    function setupSearch(searchInputId, tableBodyId) {
        const searchInput = document.getElementById(searchInputId);
        const tableBody = document.getElementById(tableBodyId);
        const rows = tableBody.querySelectorAll('.supervisor-row');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            rows.forEach(row => {
                const supervisorName = row.cells[0].textContent.toLowerCase();
                const shouldShow = supervisorName.includes(searchTerm);
                row.style.display = shouldShow ? '' : 'none';
            });
        });
    }

    setupSearch('latestSupervisorSearch', 'latestSupervisorTableBody');
    setupSearch('allTimeSupervisorSearch', 'allTimeSupervisorTableBody');

    // Supervisor row click for caller profit chart
    document.querySelectorAll('.supervisor-row').forEach(row => {
        row.addEventListener('click', function() {
            const supervisor = this.dataset.supervisor;
            const callers = JSON.parse(this.dataset.callers || '[]');

            if (callers.length === 0) {
                alert('No callers found for this supervisor.');
                return;
            }

            // Show modal
            const modal = document.getElementById('callerProfitModal');
            modal.querySelector('.modal-title').textContent = `Caller Profits for Supervisor: ${supervisor}`;
            modal.style.display = 'block';

            // Destroy previous chart if it exists
            if (callerProfitChart) {
                callerProfitChart.destroy();
            }

            // Prepare chart data
            const labels = callers.map(c => c.name);
            const data = callers.map(c => c.profit);

            const ctx = document.getElementById('callerProfitChart').getContext('2d');
            callerProfitChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Profit Margin ($)',
                        data: data,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y', // Horizontal bar chart
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    });

    // Close modal
    document.querySelector('.btn-close').addEventListener('click', function() {
        document.getElementById('callerProfitModal').style.display = 'none';
    });

    // Close modal functionality
    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.style.display = 'none';
        });
    });

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('callerProfitModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<!-- Modal for Caller Profit Chart -->
<div id="callerProfitModal" class="modal" style="display: none; position: fixed; z-index: 1055; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div class="modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; position: relative;">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        <h2 class="modal-title">Caller Profits</h2>
        <canvas id="callerProfitChart"></canvas>
    </div>
</div>

@endpush

@endsection