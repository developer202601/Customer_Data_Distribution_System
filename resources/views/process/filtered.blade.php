@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step active"></span>
    <span class="process-step"></span>
    <span class="process-step"></span>
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
<div class="process-preview py-4">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">{{ $vipApplied ? 'VIP Records' : 'Filtered Results' }}</h1>
                <p class="text-muted mb-1">
                    @if($vipApplied)
                    The list below contains the subset of filtered rows where <strong>CREDIT_CLASS_NAME</strong> starts with <strong>VIP</strong> (for example, "VIP" or "VIP - Gold").
                    @else
                    The dataset has been filtered to include only records where the medium is <strong>Copper</strong> or <strong>FTTH</strong>, the latest product status is <strong>OK</strong>, and the arrears value is greater than <strong>2400</strong>.
                    @endif
                </p>
                @if($filename)
                <p class="text-muted mb-0">Source file: <strong>{{ $filename }}</strong></p>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ $vipApplied ? route('process.upload.preview') : route('process.upload.create') }}" class="btn btn-outline-secondary">Back</a>
                @if($vipApplied)
                <a href="{{ route('process.upload.export', array_filter(['vip' => 1, 'search' => $searchTerm ?: null])) }}" class="btn btn-dark">Export VIP Excel</a>
                @else
                <a href="{{ route('process.upload.vip', array_filter(['search' => $searchTerm ?: null])) }}" class="btn btn-dark">VIP records</a>
                @endif
            </div>
        </div>

        @include('partials.process-toast', ['title' => 'Upload complete'])

        <div class="row g-3 process-summary-row mb-4">
            <div class="col-md-4">
                <div class="process-summary-card">
                    <span class="process-summary-label">Matching rows</span>
                    <span class="process-summary-value">{{ number_format($summary['filtered_rows']) }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="process-summary-card">
                    <span class="process-summary-label">Rows evaluated</span>
                    <span class="process-summary-value">{{ number_format($summary['total_rows']) }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="process-summary-card">
                    <span class="process-summary-label">Filtered out</span>
                    <span class="process-summary-value">{{ number_format($summary['skipped_rows']) }}</span>
                </div>
            </div>
        </div>

        @if($searchApplied)
        <p class="text-muted mb-4">
            Showing {{ number_format($displayCount) }} matching {{ \Illuminate\Support\Str::plural('row', $displayCount) }} for “{{ $searchTerm }}”.
        </p>
        @elseif($limited)
        <p class="text-muted mb-4">
            Showing the first 10 of {{ number_format($filteredCount) }} rows. Use the search to locate additional records.
        </p>
        @else
        <p class="text-muted mb-4">
            Showing {{ number_format($filteredCount) }} {{ \Illuminate\Support\Str::plural('row', $filteredCount) }}.
        </p>
        @endif

        <form method="get" class="process-search mb-4">
            @if($vipApplied)
            <input type="hidden" name="vip" value="1">
            @endif
            <div class="input-group">
                <input type="search" name="search" class="form-control" placeholder="Search by customer reference, account number, or product label" value="{{ $searchTerm }}">
                <button type="submit" class="btn btn-dark">Search</button>
            </div>
            <small class="form-text text-muted">Search is case-insensitive and matches partial values.</small>
        </form>

        @if(empty($filteredRows))
        <div class="alert alert-info" role="alert">
            @if($searchApplied && $vipApplied)
            No VIP records matched your search. Try a different customer reference, account number, or product label.
            @elseif($searchApplied)
            No records matched your search. Try a different customer reference, account number, or product label.
            @elseif($vipApplied)
            No VIP records matched the filter criteria. Upload another file or adjust the source data before trying again.
            @else
            No records matched the filter criteria. Upload another file or adjust the source data before trying again.
            @endif
        </div>
        @else
        <div class="process-table-container">
            <div class="table-responsive">
                <table class="table table-sm table-striped process-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Excel Row</th>
                            @foreach($headers as $meta)
                            <th scope="col">{{ $meta['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($filteredRows as $rowIndex => $row)
                        <tr>
                            <td>{{ $rowIndex }}</td>
                            @foreach($headers as $letter => $meta)
                            <td>{{ $row[$letter] ?? '' }}</td>
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