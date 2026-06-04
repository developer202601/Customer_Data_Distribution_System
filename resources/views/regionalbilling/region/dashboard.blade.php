@extends('layouts.cc')

@section('title', 'Regional Dashboard')

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
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Region: {{ $region }}</p>
                        <h1 class="process-upload-title mb-0">Region Dashboard</h1>
                        <p class="text-muted">Manage the region's RTOs, review incoming reports, and monitor progress.</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">RTO Admins</h5><br>
                                <p class="card-text">{{ $rtomCount }} RTO admin{{ $rtomCount === 1 ? '' : 's' }} assigned to your region.</p>
                                <a href="{{ route('rb.region.index') }}" class="btn btn-primary">Manage RTO Admins</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Reports</h5><br>
                                <p class="card-text">{{ $reportCount }} regional billing report{{ $reportCount === 1 ? '' : 's' }} with rows for your region.</p>
                                <a href="{{ route('rb.reports') }}" class="btn btn-primary">Review Reports</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Quick actions</h5><br>
                                <p class="card-text">Create a new RTO admin for the region.</p>
                                <div class="d-flex flex-column gap-2">
                                    <a href="{{ route('rb.region.create_admin') }}" class="btn btn-primary">Add RTO Admin</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><b>Latest regional billing reports for {{ $region }}</b></h5><br>
                                @if($recentReports->isEmpty())
                                    <p class="text-muted mb-0">No recent regional billing reports found for your region yet.</p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Report</th>
                                                    <th>Created</th>
                                                    <th>Rows</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($recentReports as $report)
                                                    <tr>
                                                        <td>{{ $report->token ?? 'Report #' . $report->id }}</td>
                                                        <td>{{ $report->created_at?->format('d M Y H:i') ?? '—' }}</td>
                                                        <td>{{ $report->row_count ?? 0 }}</td>
                                                        <td><a href="{{ route('rb.reports.summary', $report) }}" class="btn btn-sm btn-outline-primary">View</a></td>
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
    </div>
</div>
@endsection
