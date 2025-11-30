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
                <h1 class="process-preview-title mb-2">Group B Allocation</h1>
                <p class="text-muted mb-1">Enterprise, Wholesale, and SME segments with higher arrears thresholds are grouped into the downloads below.</p>
                <p class="text-muted mb-0">Dataset month: <strong>{{ $dataset['dataset_month'] ?? 'N/A' }}</strong></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('process.assignments.index') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                <a href="{{ route('process.assignments.group-a') }}" class="btn btn-outline-secondary" data-loader-off="1">Group A exports</a>
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


        <div class="row g-3">
            <div class="col-xl-4 col-lg-6">
                <div class="process-summary-card h-100 d-flex flex-column">
                    <div class="mb-3">
                        <span class="process-summary-label d-block">Enterprise &amp; Wholesale</span>
                        <span class="process-summary-value">{{ number_format($group['enterprise_wholesale']['count'] ?? 0) }}</span>
                    </div>
                    <p class="text-muted mb-3">Includes business lines 47, 44, 41, and 76.</p>
                    <div class="mt-auto">
                        @if(($group['enterprise_wholesale']['count'] ?? 0) === 0)
                        <span class="btn btn-outline-secondary disabled w-100" aria-disabled="true">No export available</span>
                        @else
                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => 'enterprise-wholesale']) }}" class="btn btn-dark w-100" data-loader-off="1">Download workbook</a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-6">
                <div class="process-summary-card h-100 d-flex flex-column">
                    <div class="mb-3">
                        <span class="process-summary-label d-block">SME</span>
                        <span class="process-summary-value">{{ number_format($group['sme']['count'] ?? 0) }}</span>
                    </div>
                    <p class="text-muted mb-3">Business line 31 grouped for SME operations.</p>
                    <div class="mt-auto">
                        @if(($group['sme']['count'] ?? 0) === 0)
                        <span class="btn btn-outline-secondary disabled w-100" aria-disabled="true">No export available</span>
                        @else
                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => 'sme']) }}" class="btn btn-dark w-100" data-loader-off="1">Download workbook</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection