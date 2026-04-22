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
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Report Summary</p>
                        <h1 class="process-upload-title mb-0">{{ $report->token ?? 'Report #' . $report->id }}</h1>
                    </div>
                    <div>
                        <a href="{{ route('rb.reports') }}" class="btn btn-outline-primary rounded-pill px-4">Back to Reports</a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Total Rows</h5>
                                <p class="card-text fs-4">{{ $report->row_count ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Assigned</h5>
                                <p class="card-text fs-4">{{ $assigned }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Unassigned</h5>
                                <p class="card-text fs-4">{{ $unassigned }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Hidden / Reviews</h5>
                                <p class="card-text fs-4">{{ $hidden + $reviews }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h2 class="h6 mb-3">Report rows</h2>
                    @if($reportRows->isEmpty())
                        <div class="alert alert-info">No rows available for your region in this report.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Assignment</th>
                                        <th>Agent</th>
                                        <th>Region</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reportRows as $assignment)
                                        <tr>
                                            <td>{{ $assignment->assignment ?? 'N/A' }}</td>
                                            <td>{{ $assignment->agent?->username ?? 'Unassigned' }}</td>
                                            <td>{{ $assignment->row?->region ?? '—' }}</td>
                                            <td>{{ $assignment->accepted ? 'Accepted' : ($assignment->rejected ? 'Rejected' : 'Pending') }}</td>
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
@endsection