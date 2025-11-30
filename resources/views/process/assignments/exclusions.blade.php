@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
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
                <h1 class="process-preview-title mb-2">Exclusion summary</h1>
                <p class="text-muted mb-1">Review the most recent exclusion run and download the workbook containing all excluded rows.</p>
                <p class="text-muted mb-0">Dataset month: <strong>{{ $dataset['dataset_month'] ?? 'N/A' }}</strong></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('process.assignments.index') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                @if(($summary['total_excluded'] ?? 0) > 0)
                <a href="{{ route('process.assignments.download', ['group' => 'exclusions', 'bucket' => 'excluded']) }}" class="btn btn-dark" data-loader-off="1">Download workbook</a>
                @endif
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

        @if(($summary['total_excluded'] ?? 0) === 0)
        <div class="alert alert-info" role="alert">
            No exclusion records have been generated yet. Upload exclusion files to produce this summary.
        </div>
        @else
        <div class="row g-3">
            <div class="col-xl-4 col-md-6">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Excluded rows</span>
                    <span class="process-summary-value">{{ number_format($summary['total_excluded'] ?? 0) }}</span>
                    <p class="text-muted mb-0">Total records currently marked as excluded.</p>
                </div>
            </div>
            @php($latest = $summary['latest_archive'] ?? null)
            <div class="col-xl-4 col-md-6">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Latest archive</span>
                    <span class="process-summary-value">{{ $latest['original_name'] ?? 'N/A' }}</span>
                    <p class="text-muted mb-0">Uploaded {{ $latest['uploaded_at'] ? \Illuminate\Support\Carbon::parse($latest['uploaded_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') : 'N/A' }}</p>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Archives stored</span>
                    <span class="process-summary-value">{{ number_format(count($summary['archives'] ?? [])) }}</span>
                    <p class="text-muted mb-0">Each upload is preserved for audit with its original filename.</p>
                </div>
            </div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Filename</th>
                        <th scope="col">Size</th>
                        <th scope="col">Uploaded at</th>
                    </tr>
                </thead>
                <tbody>
                @foreach(array_reverse($summary['archives'] ?? []) as $archive)
                    <tr>
                        <td>{{ $archive['original_name'] ?? 'Archive' }}</td>
                        <td>{{ isset($archive['size']) ? number_format($archive['size'] / 1024, 1) . ' KB' : '—' }}</td>
                        <td>{{ $archive['uploaded_at'] ? \Illuminate\Support\Carbon::parse($archive['uploaded_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') : 'N/A' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</div>
@endsection