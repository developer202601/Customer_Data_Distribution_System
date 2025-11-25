@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
    <span class="process-step active"></span>
    <span class="process-step"></span>
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
<div class="process-preview p-4 p-lg-5 shadow-sm">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">Filtered-out records</h1>
                <p class="text-muted mb-1">Rows removed by the initial eligibility filters (medium, product status, arrears) remain available here for audit.</p>
                @if(($dataset['original_filename'] ?? null))
                <p class="text-muted mb-0">Source file: <strong>{{ $dataset['original_filename'] }}</strong></p>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('process.assignments.exclusions') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => 'filtered-out']) }}" class="btn btn-dark" data-loader-off="1">Download workbook</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Total filtered-out</span>
                    <span class="process-summary-value">{{ number_format($overallCount) }}</span>
                    <p class="text-muted mb-0">Rows removed by the initial filter pass.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Matching rows</span>
                    <span class="process-summary-value">{{ number_format($matchingCount) }}</span>
                    @if($searchApplied)
                    <p class="text-muted mb-0">Results for “{{ $searchTerm }}”.</p>
                    @else
                    <p class="text-muted mb-0">All filtered-out rows.</p>
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Rows shown</span>
                    <span class="process-summary-value">{{ number_format($displayCount) }}</span>
                    @if($limited)
                    <p class="text-muted mb-0">Showing first {{ number_format($displayCount) }} of {{ number_format($matchingCount) }}. Refine search to narrow the list.</p>
                    @else
                    <p class="text-muted mb-0">Full result set displayed.</p>
                    @endif
                </div>
            </div>
        </div>

        <form method="get" class="process-search mb-4">
            <div class="input-group">
                <input type="search" name="search" class="form-control" placeholder="Search by customer reference, account number, product label, or reason" value="{{ $searchTerm }}">
                <button type="submit" class="btn btn-dark">Search</button>
            </div>
            <small class="form-text text-muted">Search is case-insensitive and matches partial values across customer reference, account number, product label, and reason.</small>
        </form>

        @if(empty($rows))
        <div class="alert alert-info" role="alert">
            @if($searchApplied)
            No filtered-out rows matched your search. Try another reference, account number, product label, or adjust the search term.
            @else
            No filtered-out rows are available at the moment. Upload a dataset and run the filters again.
            @endif
        </div>
        @else
        <div class="process-table-container">
            <div class="table-responsive">
                <table class="table table-sm table-striped process-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Reason</th>
                            <th scope="col">Excel Row</th>
                            @foreach($headers as $meta)
                            <th scope="col">{{ $meta['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $entry)
                        <tr>
                            <td>{{ $entry['reason'] ?? 'Filtered out by eligibility rules.' }}</td>
                            <td>{{ $entry['row_index'] ?? '' }}</td>
                            @foreach($headers as $letter => $meta)
                            <td>{{ $entry['columns'][$letter] ?? '' }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
