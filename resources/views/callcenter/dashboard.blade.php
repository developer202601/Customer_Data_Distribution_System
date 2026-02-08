@extends('layouts.cc')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="content">
    {{-- Removed Access Policies / Next Steps from overview per request --}}

    @if($userStats->isNotEmpty())
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                            <div>
                                <p class="text-uppercase text-muted small mb-1">Call Center coverage</p>
                                <h3 class="process-upload-title h5 mb-0">Callers at a glance</h3>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="text-muted small">Month: {{ $monthLabel }}</div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="View mode" id="ccViewMode">
                                    <button type="button" class="btn btn-outline-secondary active" data-mode="month">This month</button>
                                    <button type="button" class="btn btn-outline-secondary" data-mode="all">All time</button>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6 col-md-3">
                                <a href="#" class="text-decoration-none text-body payment-card" data-type="pending" role="button">
                                    <div class="border rounded-2 p-3 h-100 bg-white">
                                        <div class="small text-muted text-uppercase">Pending payments (this month)</div>
                                        <div class="h4 mt-2">{{ number_format($pendingPaymentsThisMonth ?? 0) }}</div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <a href="#" class="text-decoration-none text-body payment-card" data-type="overdue" role="button">
                                    <div class="border rounded-2 p-3 h-100 bg-white">
                                        <div class="small text-muted text-uppercase">Overdue payments</div>
                                        <div class="h4 mt-2">{{ number_format($overduePayments ?? 0) }}</div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <a href="#" class="text-decoration-none text-body" data-bs-toggle="modal" data-bs-target="#unassignedModal" role="button">
                                    <div class="border rounded-2 p-3 h-100 bg-white">
                                        <div class="small text-muted text-uppercase">Callers not assigned (this month)</div>
                                        <div class="h4 mt-2">{{ number_format($unassigned_callers_month_count ?? 0) }}</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <p class="text-muted small mb-3">This table shows performance metrics for active callers. Coverage represents the percentage of total assigned customers each caller has contacted. Pending payments may include follow-ups from previous months.</p>
                        <div class="table-responsive cc-table-container">
                            <table class="table table-borderless table-hover align-middle mb-0">
                                <thead>
                                    <tr class="text-uppercase text-muted small">
                                        <th scope="col">Caller</th>
                                        <th scope="col" title="Unique customers contacted this month/all time">Customers contacted</th>
                                        <th scope="col" title="Percentage of total assigned rows this caller has contacted">Coverage</th>
                                        <th scope="col">Calls</th>
                                        <th scope="col">Payments after call</th>
                                        <th scope="col">Amount received</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($userStats as $stats)
                                        <tr class="caller-row" data-user-id="{{ $stats['id'] }}">
                                            <td>
                                                <strong>{{ $stats['name'] }}</strong>
                                                <div class="text-muted small">
                                                    @if($stats['username'])
                                                        {{ '@' . $stats['username'] }}
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="cc-month-primary fw-bold">{{ number_format($stats['assigned_rows_month'] ?? 0) }}</div>
                                                <div class="cc-all-primary fw-bold">{{ number_format($stats['assigned_rows_all'] ?? 0) }}</div>
                                                <div class="cc-month-secondary small text-muted">All-time: {{ number_format($stats['assigned_rows_all'] ?? 0) }}</div>
                                                <div class="cc-all-secondary small text-muted">This month: {{ number_format($stats['assigned_rows_month'] ?? 0) }}</div>
                                            </td>
                                            <td>
                                                <div class="cc-month-primary fw-bold">{{ $stats['coverage_month'] }}%</div>
                                                <div class="cc-all-primary fw-bold">{{ $stats['coverage_all'] }}%</div>
                                                <div class="cc-month-secondary small text-muted">All-time: {{ $stats['coverage_all'] }}%</div>
                                                <div class="cc-all-secondary small text-muted">This month: {{ $stats['coverage_month'] }}%</div>
                                            </td>
                                            <td>
                                                <div class="cc-month-primary fw-bold">{{ number_format($stats['calls_month']) }}</div>
                                                <div class="cc-all-primary fw-bold">{{ number_format($stats['calls_all']) }}</div>
                                                <div class="cc-month-secondary small text-muted">All-time: {{ number_format($stats['calls_all']) }}</div>
                                                <div class="cc-all-secondary small text-muted">This month: {{ number_format($stats['calls_month']) }}</div>
                                            </td>
                                            <td>
                                                <div class="cc-month-primary fw-bold">{{ number_format($stats['payments_month']) }}</div>
                                                <div class="cc-all-primary fw-bold">{{ number_format($stats['payments_all']) }}</div>
                                                <div class="cc-month-secondary small text-muted">All-time: {{ number_format($stats['payments_all']) }}</div>
                                                <div class="cc-all-secondary small text-muted">This month: {{ number_format($stats['payments_month']) }}</div>
                                            </td>
                                            <td>
                                                <div class="cc-month-primary fw-bold">{{ number_format($stats['payments_amount_month'] ?? 0, 2) }}</div>
                                                <div class="cc-all-primary fw-bold">{{ number_format($stats['payments_amount_all'] ?? 0, 2) }}</div>
                                                <div class="cc-month-secondary small text-muted">All-time: {{ number_format($stats['payments_amount_all'] ?? 0, 2) }}</div>
                                                <div class="cc-all-secondary small text-muted">This month: {{ number_format($stats['payments_amount_month'] ?? 0, 2) }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    
        <!-- Payments modal -->
        @push('styles')
        <style nonce="{{ $cspNonce ?? '' }}">
            /* Ensure modal and backdrop appear above fixed navbar */
            .modal-backdrop {
                z-index: 1990 !important;
            }
            .modal {
                z-index: 2000 !important;
            }
            .caller-row {
                cursor: pointer;
            }
            .caller-row:hover {
                background-color: rgba(0,0,0,0.02);
            }
        </style>
        @endpush

        <div class="modal fade" id="paymentsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentsModalLabel">Payments</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="paymentsModalType" class="mb-2 text-muted"></div>
                        <div class="table-responsive cc-table-container">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Account / Assignment</th>
                                        <th>Expected date</th>
                                        <th>Assigned caller</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentsModalBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unassigned callers modal -->
        <div class="modal fade" id="unassignedModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Callers not assigned this month</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Caller</th><th class="text-muted">Username</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($unassigned_callers_month ?? [] as $u)
                                    <tr>
                                        <td>{{ $u['name'] }}</td>
                                        <td class="text-muted">{{ $u['username'] ? '@'.$u['username'] : '—' }}</td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="2" class="text-center text-muted">No unassigned callers this month</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
        // View mode toggle: switch primary/secondary month vs all-time
        function setCCViewMode(mode) {
            document.querySelectorAll('.cc-table-container').forEach(function (el) {
                el.setAttribute('data-mode', mode);
            });
            // update button active state
            document.querySelectorAll('#ccViewMode [data-mode]').forEach(function (b) {
                b.classList.toggle('active', b.getAttribute('data-mode') === mode);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('#ccViewMode [data-mode]').forEach(function (b) {
                b.addEventListener('click', function () {
                    setCCViewMode(this.getAttribute('data-mode'));
                });
            });
            // default
            setCCViewMode('month');
        });

        // Caller 7-day chart logic
        function ensureChartJsLoaded(cb) {
            if (typeof Chart !== 'undefined') return cb();
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            s.onload = cb;
            document.head.appendChild(s);
        }

        let callerChartInstance = null;
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('callerChartModal');
            const titleEl = document.getElementById('callerChartModalLabel');
            const ctx = document.getElementById('callerChartCanvas').getContext('2d');
            const urlBase = "{{ url('cc/caller') }}";

            document.querySelectorAll('.caller-row').forEach(row => row.addEventListener('click', function () {
                const userId = this.getAttribute('data-user-id');
                titleEl.textContent = 'Calls in last 7 days';
                ctx.clearRect(0,0,ctx.canvas.width, ctx.canvas.height);
                fetch(urlBase + '/' + encodeURIComponent(userId) + '/calls7', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(payload => {
                        // show modal first, then render chart on shown to ensure correct sizing
                        const bs = new bootstrap.Modal(modalEl);
                        function onShown() {
                            try {
                                ensureChartJsLoaded(function () {
                                    if (callerChartInstance) callerChartInstance.destroy();
                                    callerChartInstance = new Chart(ctx, {
                                        type: 'bar',
                                        data: {
                                            labels: payload.labels || [],
                                            datasets: [{
                                                label: 'Calls',
                                                data: payload.data || [],
                                                backgroundColor: 'rgba(54,162,235,0.9)',
                                                borderRadius: 4
                                            }]
                                        },
                                        options: {
                                            animation: false,
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: { grid: { display: false }, ticks: { color: '#666' } },
                                                y: { grid: { display: false }, beginAtZero: true, ticks: { stepSize: 1, color: '#666' } }
                                            },
                                            plugins: { legend: { display: false } }
                                        }
                                    });
                                });
                            } catch (e) {
                                console.error(e);
                            }
                            modalEl.removeEventListener('shown.bs.modal', onShown);
                        }

                        modalEl.addEventListener('shown.bs.modal', onShown);
                        // cleanup chart on hide
                        modalEl.addEventListener('hidden.bs.modal', function cleanup() {
                            if (callerChartInstance) { callerChartInstance.destroy(); callerChartInstance = null; }
                            modalEl.removeEventListener('hidden.bs.modal', cleanup);
                        });

                        bs.show();
                        // ensure this chart modal sits above other modals/backdrops
                        try {
                            modalEl.style.zIndex = 3000;
                            // adjust the most recent backdrop to sit below the modal
                            const backs = document.querySelectorAll('.modal-backdrop');
                            if (backs && backs.length) {
                                const last = backs[backs.length - 1];
                                last.style.zIndex = 2990;
                            }
                        } catch (e) {
                            // ignore
                        }
                    }).catch(() => {
                        alert('Failed to load chart data');
                    });
            }));
        });
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.payment-card');
            const modalEl = document.getElementById('paymentsModal');
            const modalBody = document.getElementById('paymentsModalBody');
            const modalType = document.getElementById('paymentsModalType');

            cards.forEach(c => c.addEventListener('click', function (e) {
                e.preventDefault();
                const type = this.getAttribute('data-type');
                modalType.textContent = type === 'pending' ? 'Pending payments (this month)' : 'Overdue payments';
                modalBody.innerHTML = '<tr><td colspan="3">Loading…</td></tr>';
                fetch("{{ route('cc.payments.list') }}?type="+encodeURIComponent(type), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(r => r.json()).then(data => {
                    modalBody.innerHTML = '';
                    (data.items || []).forEach(it => {
                        const acc = it.account ? it.account : ('#' + it.assignment_id);
                        const assigned = it.assigned_user_name ? it.assigned_user_name : (it.assigned_user_id ? 'ID:'+it.assigned_user_id : '-');
                        const row = `<tr><td>${acc}</td><td>${it.payment_expected_at || '-'}</td><td>${assigned}</td></tr>`;
                        modalBody.insertAdjacentHTML('beforeend', row);
                    });
                    if (!data.items || data.items.length === 0) {
                        modalBody.innerHTML = '<tr><td colspan="3">No records</td></tr>';
                    }
                }).catch(err => {
                    modalBody.innerHTML = '<tr><td colspan="3">Error loading data</td></tr>';
                });
                var bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();
            }));
        });
        </script>
        @endpush

        <!-- Caller chart modal -->
        <div class="modal fade" id="callerChartModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="callerChartModalLabel">Calls</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <canvas id="callerChartCanvas" width="600" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
</div>
@endsection
