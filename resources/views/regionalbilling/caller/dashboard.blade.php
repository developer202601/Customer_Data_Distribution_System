@extends('layouts.cc')

@section('title', 'Caller Dashboard')

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
                        <p class="text-uppercase text-muted mb-1">Regional Billing</p>
                        <h1 class="process-upload-title mb-0">Caller Dashboard</h1>
                        <p class="text-muted small mb-0">Overview of your assigned rows and recent activity.</p>
                    </div>
                    <a href="{{ route('rb.assignments.manage') }}" class="btn btn-primary rounded-pill px-4">Go to Assigned Rows</a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 bg-light rounded-4 h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Total assigned</p>
                                <h2 class="h5 mb-0">{{ number_format($totalAssigned ?? 0) }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light rounded-4 h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Pending acceptance</p>
                                <h2 class="h5 mb-0">{{ number_format($pendingAcceptance ?? 0) }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light rounded-4 h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Active (accepted)</p>
                                <h2 class="h5 mb-0">{{ number_format($pendingAccepted ?? 0) }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light rounded-4 h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Completed</p>
                                <h2 class="h5 mb-0">{{ number_format($completed ?? 0) }}</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light rounded-4 h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Rejected</p>
                                <h2 class="h5 mb-0">{{ number_format($rejected ?? 0) }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light rounded-4 h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small mb-1">Latest report</p>
                                <h2 class="h5 mb-0">{{ $latestReportLabel ?? '—' }}</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Recent assigned rows</h5>
                        @if(($recentAssignments ?? collect())->isEmpty())
                            <p class="text-muted mb-0">No assigned rows yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Report</th>
                                            <th>Account</th>
                                            <th>Customer Ref</th>
                                            <th>Arrears</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentAssignments as $assignment)
                                            @php $row = $assignment->row; @endphp
                                            <tr>
                                                <td>{{ $assignment->report?->dataset_month ?? ('Report #' . $assignment->call_center_report_id) }}</td>
                                                <td>{{ $row?->account_num ?? '—' }}</td>
                                                <td>{{ $row?->customer_ref ?? '—' }}</td>
                                                <td>{{ $row?->new_arrears_value ?? '—' }}</td>
                                                <td>{{ $assignment->status ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection