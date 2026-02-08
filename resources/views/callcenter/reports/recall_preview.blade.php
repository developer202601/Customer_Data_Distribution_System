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
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <p class="text-uppercase text-muted small mb-1">Call center</p>
                        <h1 class="h4 mb-0">Recall preview</h1>
                        <p class="text-muted small mb-0">Preview the first 50 assignments that will be removed if you undo this report.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.reports') }}" class="btn btn-outline-secondary btn-sm">Back to reports</a>
                    </div>
                </div>

                <div class="row g-3 mt-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-uppercase small text-muted mb-1">Assignments</p>
                                <h3 class="h5 mb-0">{{ number_format($count) }}</h3>
                                <p class="small text-muted mb-0">Total assigned rows in this report</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h2 class="h6 text-uppercase text-muted">Sample rows</h2>
                    @if(empty($sample))
                        <p class="text-muted small mb-0">No assigned rows were found for this report.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr>
                                        <th>Assignment</th>
                                        <th>Row</th>
                                        <th>Agent</th>
                                        <th>Status</th>
                                        <th>Accepted</th>
                                        <th>Rejected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sample as $row)
                                        <tr>
                                            <td class="small">{{ $row['assignment_id'] }}</td>
                                            <td class="small">{{ $row['row_id'] }}</td>
                                            <td class="small">{{ $row['agent'] ?? '—' }}</td>
                                            <td class="small">{{ ucfirst($row['status'] ?? 'pending') }}</td>
                                            <td class="small">{{ $row['accepted'] ? 'Yes' : 'No' }}</td>
                                            <td class="small">{{ $row['rejected'] ? 'Yes' : 'No' }}</td>
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
