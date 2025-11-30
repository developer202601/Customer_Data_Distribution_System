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
<div class="process-preview p-4 p-lg-5 shadow-sm">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">Group A Allocation</h1>
                <p class="text-muted mb-1">Retail and Micro Business records that matched the arrears rules have been split into the operational quotas below.</p>
                <p class="text-muted mb-0">Dataset month: <strong>{{ $dataset['dataset_month'] ?? 'N/A' }}</strong> · Evaluated rows: {{ number_format($group['input'] ?? 0) }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('process.assignments.index') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                <a href="{{ route('process.assignments.group-b') }}" class="btn btn-outline-secondary" data-loader-off="1">Group B exports</a>
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


        @php($quotas = $group['quotas'] ?? [])
        @php($region = $group['region'] ?? [])
        <div class="group-section mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Quota allocation</h2>
                <span class="text-muted">{{ number_format($group['input'] ?? 0) }} records evaluated</span>
            </div>

            <div class="row g-3">
                @forelse($quotas as $key => $quota)
                <div class="col-xl-3 col-md-6">
                    <div class="process-summary-card h-100 d-flex flex-column">
                        <div class="mb-3">
                            <span class="process-summary-label d-block">{{ $quota['label'] }}</span>
                            <span class="process-summary-value">{{ number_format($quota['actual'] ?? 0) }}</span>
                        </div>
                        <p class="text-muted mb-3">Target {{ number_format($quota['target'] ?? 0) }} · {{ ($quota['actual'] ?? 0) >= ($quota['target'] ?? 0) ? 'Quota met.' : ('Short by ' . number_format(max(($quota['target'] ?? 0) - ($quota['actual'] ?? 0), 0)) . '.') }}</p>
                        <div class="mt-auto">
                            <a href="{{ route('process.assignments.download', ['group' => 'group-a', 'bucket' => $key]) }}" class="btn btn-dark w-100" data-loader-off="1">Download Excel</a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="alert alert-info" role="alert">No Group A quotas available. Regenerate assignments after uploading a dataset.</div>
                </div>
                @endforelse
                <div class="col-xl-3 col-md-6">
                    <div class="process-summary-card h-100 d-flex flex-column">
                        <div class="mb-3">
                            <span class="process-summary-label d-block">{{ $region['label'] ?? 'Region Billing Centre' }}</span>
                            <span class="process-summary-value">{{ number_format($region['actual'] ?? 0) }}</span>
                        </div>
                        <p class="text-muted mb-3">Contains all remaining Group&nbsp;A records not allocated to a quota. Download the combined workbook from the Region Billing Centre page.</p>
                        <div class="mt-auto">
                            <a href="{{ route('process.assignments.region') }}" class="btn btn-success w-100 text-white" data-loader-off="1">Open Region Billing Centre</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection