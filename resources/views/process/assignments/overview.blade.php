@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">Assignments overview</h1>
                <p class="text-muted mb-0">Choose which allocation set you want to review and download. Group A covers Retail &amp; Micro Business quotas; Group B covers all other segments.</p>
                @if(($dataset['original_filename'] ?? null))
                <p class="text-muted mb-0 mt-2">Active dataset: <strong>{{ $dataset['original_filename'] }}</strong> ({{ number_format(count($dataset['rows'] ?? [])) }} rows)</p>
                @endif
                @if($generatedAt)
                <p class="text-muted mb-0">Assignments generated on {{ $generatedAt }}.</p>
                @endif
            </div>
        </div>

        @if(session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
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

        <div class="row g-4">
            <div class="col-xl-4 col-md-6">
                <a href="{{ route('process.assignments.group-a') }}" class="dashboard-card h-100" role="button" aria-label="Go to Group A assignments">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">Group A (Retail &amp; Micro)</h2>
                        <p class="dashboard-card-description mb-1">Manage Call Center Staff, Call Center, Staff quotas, and Region Billing Centre remainder.</p>
                        <p class="text-muted mb-0 small">{{ number_format($groupTotals['group_a'] ?? 0) }} rows eligible.</p>
                    </div>
                </a>
            </div>
            <div class="col-xl-4 col-md-6">
                <a href="{{ route('process.assignments.group-b') }}" class="dashboard-card h-100" role="button" aria-label="Go to Group B assignments">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">Group B (Other Segments)</h2>
                        <p class="dashboard-card-description mb-1">Download Enterprise &amp; Wholesale bundles plus segment-specific exports.</p>
                        <p class="text-muted mb-0 small">{{ number_format($groupTotals['group_b'] ?? 0) }} rows eligible.</p>
                    </div>
                </a>
            </div>
            <div class="col-xl-4 col-md-6">
                <a href="{{ route('process.assignments.exclusions') }}" class="dashboard-card h-100" role="button" aria-label="Go to exclusions summary">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">Exclusions</h2>
                        @if(!$latestExclusion && !$filteredOutSummary)
                        <p class="text-muted mb-0">No exclusion files have been applied to this dataset yet.</p>
                        @else
                        @if($latestExclusion)
                        <p class="dashboard-card-description mb-1">{{ number_format($latestExclusion['removed']) }} records removed across {{ $latestExclusion['files_count'] }} file{{ $latestExclusion['files_count'] === 1 ? '' : 's' }}.</p>
                        @endif
                        @if($filteredOutSummary)
                        <p class="dashboard-card-description mb-1">{{ number_format($filteredOutSummary['count']) }} rows filtered out before exclusions.</p>
                        @endif
                        <p class="text-muted mb-0 small">Tap to open the exclusion summary and download the workbook.</p>
                        @endif
                    </div>
                </a>
            </div>
            <div class="col-xl-4 col-md-6">
                <a href="{{ route('process.upload.vip') }}" class="dashboard-card h-100" role="button" aria-label="Review VIP records">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">VIP Records</h2>
                        <p class="dashboard-card-description mb-1">Open the dedicated VIP view to inspect rows with VIP credit classes and export their workbook.</p>
                        @if($vipSummary)
                        <p class="text-muted mb-0 small">{{ number_format($vipSummary['count']) }} VIP row{{ $vipSummary['count'] === 1 ? '' : 's' }} available.</p>
                        @else
                        <p class="text-muted mb-0 small">Upload a dataset to review VIP records.</p>
                        @endif
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection