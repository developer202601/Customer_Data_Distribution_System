@extends('layouts.cc')

@section('title', 'Regional Billing Reports')

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
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Reports</p>
                        <h1 class="process-upload-title mb-0">Current report progress</h1>
                        <p class="text-muted">Showing the latest regional billing reports for {{ $region }}.</p>
                    </div>
                    <div>
                        <a href="{{ route('rb.reports.history') }}" class="btn btn-outline-primary rounded-pill px-4">View History</a>
                    </div>
                </div>

                @if($reports->isEmpty())
                    <div class="alert alert-info mb-0">No regional billing reports available for your region yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Report</th>
                                    <th>Created</th>
                                    <th>Rows</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reports as $report)
                                    <tr>
                                        <td>{{ $report->token ?? 'Report #' . $report->id }}</td>
                                        <td>{{ $report->created_at?->format('d M Y H:i') ?? '—' }}</td>
                                        <td>{{ $report->row_count ?? 0 }}</td>
                                        <td>{{ $report->created_at?->diffForHumans() ?? 'New' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('rb.reports.summary', $report) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        </td>
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
@endsection
