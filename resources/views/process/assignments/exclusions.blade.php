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
                <h1 class="process-preview-title mb-2">Exclusion summary</h1>
                <p class="text-muted mb-1">Review the most recent exclusion run and download the workbook containing all excluded rows.</p>
                @if(($dataset['original_filename'] ?? null))
                <p class="text-muted mb-0">Active dataset: <strong>{{ $dataset['original_filename'] }}</strong></p>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('process.assignments.index') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                @if($latestExclusion || $filteredOutSummary)
                <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => 'latest-exclusions']) }}" class="btn btn-dark" data-loader-off="1">Download workbook</a>
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

        @if(!$latestExclusion)
        <div class="alert alert-info" role="alert">
            No exclusion records have been generated yet. Upload exclusion files to produce this summary.
        </div>
        @else
        <div class="row g-3">
            <div class="col-xl-4 col-md-6">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Records removed</span>
                    <span class="process-summary-value">{{ number_format($latestExclusion['removed']) }}</span>
                    <p class="text-muted mb-0">Rows eliminated during the most recent exclusion import.</p>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Source files</span>
                    <span class="process-summary-value">{{ number_format($latestExclusion['files_count']) }}</span>
                    <p class="text-muted mb-0">{{ $latestExclusion['files_count'] === 1 ? 'File' : 'Files' }} processed on {{ $latestExclusion['timestamp'] ?? 'N/A' }}.</p>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="process-summary-card h-100">
                    <span class="process-summary-label">Rows in export</span>
                    <span class="process-summary-value">{{ number_format($latestExclusion['records_total']) }}</span>
                    <p class="text-muted mb-0">Total row count available in the exclusions workbook.</p>
                </div>
            </div>
        </div>
        @endif

        @if($filteredOutSummary)
        <div class="alert alert-secondary mt-4" role="alert">
            {{ number_format($filteredOutSummary['count']) }} rows were filtered out before exclusions (medium, status, or arrears rules). They are included as a dedicated worksheet in the download.
        </div>
        @endif

    </div>
</div>
@endsection
