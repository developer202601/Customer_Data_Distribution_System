@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
    <span class="process-step active"></span>
</div>
@if(session('user.is_admin'))
<a href="#" class="btn btn-outline-secondary">Configurations</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
@php($exportStatuses = $exports ?? [])
<div class="process-preview p-4 p-lg-5 shadow-sm">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">Region Billing Centre</h1>
                <p class="text-muted mb-1">All accounts left in the Region Billing Centre bucket after quotas and exclusions are merged into a single workbook.</p>
                <p class="text-muted mb-0">Dataset month: <strong>{{ $dataset['dataset_month'] ?? 'N/A' }}</strong> · Total rows: {{ number_format($summary['count'] ?? 0) }} @if($search !== '') · Matches for &ldquo;{{ $search }}&rdquo;: {{ number_format($rows->total()) }} @endif</p>
            </div>
            @php($regionStatus = $exportStatuses['region-billing'] ?? [])
            @php($regionState = $regionStatus['status'] ?? 'processing')
            @php($regionReady = $regionState === 'ready')
            @php($regionFailed = $regionState === 'failed')
            <div class="d-flex flex-wrap gap-2">
                @if($regionFailed)
                <button type="button" class="btn btn-outline-danger" disabled>Generation failed</button>
                @elseif($regionReady)
                <a href="{{ route('process.assignments.download', ['group' => 'region', 'bucket' => 'region-billing']) }}" class="btn btn-dark" target="_blank" rel="noopener noreferrer" data-loader-off="1">Download Region Billing Excel</a>
                @else
                <button type="button" class="btn btn-dark" disabled>Generating…</button>
                @endif
                @if(($summary['count'] ?? 0) > 0)
                <a href="{{ route('process.assignments.download', ['group' => 'region', 'bucket' => 'region-billing', 'fresh' => 1]) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener noreferrer" data-loader-off="1">Download live</a>
                @endif
                <a href="{{ route('process.assignments.index') }}" class="btn btn-outline-secondary" data-loader-off="1">Back to overview</a>
            </div>
        </div>

        @if(session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(($summary['count'] ?? 0) === 0)
        <div class="alert alert-info" role="alert">
            No Region Billing Centre records are available. Upload a new master dataset to refresh the assignments.
        </div>
        @else
        <form method="get" action="{{ route('process.assignments.region') }}" class="row g-2 align-items-end mb-4" data-loader-off="1">
            <div class="col-12 col-lg-6 col-xxl-4">
                <label for="region-search" class="form-label">Search by customer reference, account number, or product</label>
                <input type="text" class="form-control" id="region-search" name="search" value="{{ $search }}" placeholder="e.g. 0712345678 or Broadband" autocomplete="off">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
            @if($search !== '')
            <div class="col-auto">
                <a href="{{ route('process.assignments.region') }}" class="btn btn-outline-secondary" data-loader-off="1">Clear</a>
            </div>
            @endif
        </form>

        <div class="process-table-container">
            <div class="table-responsive">
                <table class="table table-striped process-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Customer Reference</th>
                        <th scope="col">Account Number</th>
                        <th scope="col">Product Label</th>
                        <th scope="col">Business Line</th>
                        <th scope="col" class="text-end">New Arrears (Rs.)</th>
                        <th scope="col">Assigned To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->customer_ref ?? '—' }}</td>
                        <td>{{ $row->account_num ?? '—' }}</td>
                        <td>{{ $row->product_label ?? '—' }}</td>
                        <td>{{ $row->slt_business_line_value ?? '—' }}</td>
                        <td class="text-end">{{ $row->new_arrears_value !== null ? number_format((float) $row->new_arrears_value, 2) : '—' }}</td>
                        <td>{{ $row->assigned_to ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No Region Billing Centre records matched your filters.</td>
                    </tr>
                    @endforelse
                </tbody>
                </table>
            </div>
        </div>

        @if($rows->hasPages())
        <div class="mt-3">
            {{ $rows->links('pagination::bootstrap-5') }}
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
