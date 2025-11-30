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
                <p class="text-muted mb-1">Choose which allocation set you want to review and download. Group&nbsp;A covers Retail &amp; Micro Business quotas; Group&nbsp;B covers Enterprise, Wholesale, and SME segments.</p>
                <p class="text-muted mb-0">Dataset month: <strong>{{ $dataset['dataset_month'] ?? 'N/A' }}</strong> · Total rows: {{ number_format($dataset['row_count'] ?? 0) }} · Excluded: {{ number_format($dataset['excluded_count'] ?? 0) }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form action="{{ route('process.assignments.regenerate') }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">Recalculate assignments</button>
                </form>
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

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
            <div class="col">
                <a href="{{ route('process.assignments.group-a') }}" class="dashboard-card h-100" role="button" aria-label="Go to Group A assignments">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">Group A (Retail &amp; Micro)</h2>
                        <p class="dashboard-card-description mb-2">Manage Call Center Staff, Call Center, Staff quotas, and Region Billing Centre remainder.</p>
                        <ul class="list-unstyled text-muted small mb-0">
                            <li>Call Center Staff: {{ number_format($groupA['quotas']['call-center-staff']['actual'] ?? 0) }} / {{ number_format($groupA['quotas']['call-center-staff']['target'] ?? 0) }}</li>
                            <li>Call Center: {{ number_format($groupA['quotas']['call-center']['actual'] ?? 0) }} / {{ number_format($groupA['quotas']['call-center']['target'] ?? 0) }}</li>
                            <li>Staff: {{ number_format($groupA['quotas']['staff']['actual'] ?? 0) }} / {{ number_format($groupA['quotas']['staff']['target'] ?? 0) }}</li>
                        </ul>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="{{ route('process.assignments.group-b') }}" class="dashboard-card h-100" role="button" aria-label="Go to Group B assignments">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">Group B (Other Segments)</h2>
                        <p class="dashboard-card-description mb-2">Download Enterprise &amp; Wholesale bundles plus SME exports.</p>
                        <ul class="list-unstyled text-muted small mb-0">
                            <li>Enterprise &amp; Wholesale: {{ number_format($groupB['enterprise_wholesale']['count'] ?? 0) }}</li>
                            <li>SME: {{ number_format($groupB['sme']['count'] ?? 0) }}</li>
                        </ul>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="{{ route('process.assignments.region') }}" class="dashboard-card h-100" role="button" aria-label="Go to Region Billing Centre records">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">Region Billing Centre</h2>
                        <p class="dashboard-card-description mb-2">Single workbook covering all records left in the Region Billing Centre bucket.</p>
                        <p class="text-muted mb-0 small">Available rows: {{ number_format($region['count'] ?? 0) }}</p>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="{{ route('process.assignments.exclusions') }}" class="dashboard-card h-100" role="button" aria-label="Go to exclusions summary">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">Exclusions</h2>
                        <p class="dashboard-card-description mb-2">Review the latest exclusion run and download the excluded records workbook.</p>
                        <p class="text-muted mb-0 small">Total excluded rows: {{ number_format($exclusions['total_excluded'] ?? 0) }}</p>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="{{ route('process.assignments.vip') }}" class="dashboard-card h-100" role="button" aria-label="Go to VIP records">
                    <div class="dashboard-card-body">
                        <h2 class="dashboard-card-title">VIP Records</h2>
                        <p class="dashboard-card-description mb-2">Review unassigned high-priority accounts where the credit class begins with &ldquo;VIP&rdquo;.</p>
                        <p class="text-muted mb-0 small">Available VIP rows: {{ number_format($vip['count'] ?? 0) }}</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection