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
                <p class="text-muted mb-1">Segments outside Retail and Micro Business with latest bill amounts &ge; 5,000 are grouped for enterprise, wholesale, and other segment exports.</p>
                @if(($dataset['original_filename'] ?? null))
                <p class="text-muted mb-0">Active dataset: <strong>{{ $dataset['original_filename'] }}</strong> ({{ number_format(count($dataset['rows'] ?? [])) }} rows)</p>
                @endif
                @if($generatedAt)
                <p class="text-muted mb-0">Assignments generated on {{ $generatedAt }}.</p>
                @endif
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


        @php($bundle = $assignments['enterprise_wholesale'] ?? [])
        @php($categories = $bundle['categories'] ?? [])
        @php($segments = $assignments['segments'] ?? [])
        @php($ignored = $assignments['ignored'] ?? [])

        <div class="row g-3">
            <div class="col-xl-4 col-lg-6">
                <div class="process-summary-card h-100 d-flex flex-column">
                    <div class="mb-3">
                        <span class="process-summary-label d-block">Enterprise &amp; Wholesale bundle</span>
                        <span class="process-summary-value">{{ number_format($bundle['count'] ?? 0) }}</span>
                    </div>
                    @if(empty($categories))
                    <p class="text-muted mb-3">No eligible Enterprise or Wholesale records detected.</p>
                    @else
                    <div class="small text-muted mb-3">
                        @foreach($categories as $category)
                        <div class="mb-2">
                            <strong>{{ $category['label'] ?? 'Category' }}</strong>
                            <span class="fw-normal">({{ number_format($category['count'] ?? 0) }} rows)</span>
                            <ul class="mb-0 ps-3">
                                @forelse($category['segments'] ?? [] as $segment)
                                <li>{{ $segment['name'] }} &mdash; {{ number_format($segment['count'] ?? 0) }} rows</li>
                                @empty
                                <li>No sub-segments detected.</li>
                                @endforelse
                            </ul>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    <div class="mt-auto">
                        @if(empty($categories))
                        <span class="btn btn-outline-secondary disabled w-100" aria-disabled="true">No export available</span>
                        @else
                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => 'enterprise-wholesale']) }}" class="btn btn-dark w-100" data-loader-off="1">Download workbook</a>
                        @endif
                    </div>
                </div>
            </div>

            @forelse($segments as $segment)
            <div class="col-xl-4 col-lg-6">
                <div class="process-summary-card h-100 d-flex flex-column">
                    <div class="mb-3">
                        <span class="process-summary-label d-block">{{ $segment['name'] }}</span>
                        <span class="process-summary-value">{{ number_format($segment['count'] ?? 0) }}</span>
                    </div>
                    <p class="text-muted mb-3">Download a dedicated workbook for this segment.</p>
                    <div class="mt-auto">
                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => $segment['slug'] ?? 'segment']) }}" class="btn btn-dark w-100" data-loader-off="1">Download segment</a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="alert alert-info mb-0" role="alert">No additional Group&nbsp;B segments met the export criteria.</div>
            </div>
            @endforelse

            <div class="col-xl-4 col-lg-6">
                <div class="process-summary-card h-100 d-flex flex-column">
                    <div class="mb-3">
                        <span class="process-summary-label d-block">Ignored &lt; 5000 LATEST_BILL_MNY</span>
                        <span class="process-summary-value">{{ number_format($ignored['count'] ?? 0) }}</span>
                    </div>
                    <p class="text-muted mb-3">Rows below the Group&nbsp;B threshold are collected for audit in a separate export.</p>
                    <div class="mt-auto">
                        @if(($ignored['count'] ?? 0) === 0)
                        <span class="btn btn-outline-secondary disabled w-100" aria-disabled="true">No records ignored</span>
                        @else
                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => 'ignored-latest-bill']) }}" class="btn btn-dark w-100" data-loader-off="1">Download ignored records</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection