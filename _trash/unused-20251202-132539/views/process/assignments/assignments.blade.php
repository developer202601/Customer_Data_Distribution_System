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
<div class="process-preview p-4 p-lg-5 shadow-sm" id="group-a">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">Assignment Results</h1>
                <p class="text-muted mb-1">The remaining records have been divided into Group&nbsp;A and Group&nbsp;B using the configured business rules. Use the downloads below to retrieve each allocation sheet.</p>
                @if(($dataset['original_filename'] ?? null))
                <p class="text-muted mb-0">Active dataset: <strong>{{ $dataset['original_filename'] }}</strong> ({{ number_format($dataset['row_count'] ?? 0) }} rows)</p>
                @endif
                <p class="text-muted mb-0">Assignments generated on {{ $assignments['generated_at'] ?? now()->toDateTimeString() }}.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('process.exclusions.create') }}" class="btn btn-outline-secondary" data-loader-off="1">Back to exclusions</a>
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

        <div class="group-section mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Group A &mdash; Retail and Micro Business</h2>
                <span class="text-muted">{{ number_format($assignments['group_a']['totals']['input'] ?? 0) }} records evaluated</span>
            </div>

            <div class="row g-3">
                @foreach($assignments['group_a']['quotas'] ?? [] as $key => $quota)
                <div class="col-xl-3 col-md-6">
                    <div class="process-summary-card h-100 d-flex flex-column">
                        <div class="mb-3">
                            <span class="process-summary-label d-block">{{ $quota['label'] }}</span>
                            <span class="process-summary-value">{{ number_format($quota['actual']) }}</span>
                        </div>
                        <p class="text-muted mb-3">Target {{ number_format($quota['target']) }}. {{ $quota['actual'] >= $quota['target'] ? 'Quota met.' : ('Short by ' . number_format(max($quota['target'] - $quota['actual'], 0)) . '.') }}</p>
                        <div class="mt-auto">
                            <a href="{{ route('process.assignments.download', ['group' => 'group-a', 'bucket' => $key]) }}" class="btn btn-dark w-100" data-loader-off="1">Download Excel</a>
                        </div>
                    </div>
                </div>
                @endforeach
                <div class="col-xl-3 col-md-6">
                    <div class="process-summary-card h-100 d-flex flex-column">
                        <div class="mb-3">
                            <span class="process-summary-label d-block">{{ $assignments['group_a']['region_billing']['label'] ?? 'Region Billing Centre' }}</span>
                            <span class="process-summary-value">{{ number_format($assignments['group_a']['region_billing']['actual'] ?? 0) }}</span>
                        </div>
                        <p class="text-muted mb-3">Contains all remaining Group&nbsp;A records not allocated to a quota.</p>
                        <div class="mt-auto">
                            <a href="{{ route('process.assignments.download', ['group' => 'group-a', 'bucket' => 'region-billing']) }}" class="btn btn-success w-100 text-white" data-loader-off="1">Download Excel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="group-section" id="group-b">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Group B &mdash; Enterprise, SME, Wholesale</h2>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    @php($bundle = $assignments['group_b']['enterprise_wholesale'] ?? [])
                    @php($categories = $bundle['categories'] ?? [])
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="flex-grow-1">
                            <h3 class="h5 mb-1">Enterprise &amp; Wholesale bundle</h3>
                            <p class="text-muted mb-2">Exports Enterprise and Wholesale allocations into a single workbook with separate sheets for each category.</p>
                            @if(empty($categories))
                            <p class="text-muted mb-0 small">No eligible Enterprise or Wholesale records detected.</p>
                            @else
                            <div class="d-flex flex-column gap-3 small text-muted">
                                @foreach($categories as $category)
                                <div>
                                    <h4 class="h6 mb-1 text-uppercase">{{ $category['label'] ?? 'Category' }} <span class="fw-normal">({{ number_format($category['count'] ?? 0) }} rows)</span></h4>
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
                        </div>
                        <div>
                            @if(empty($categories))
                            <span class="btn btn-outline-secondary disabled" aria-disabled="true">No export available</span>
                            @else
                            <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => 'enterprise-wholesale']) }}" class="btn btn-dark" data-loader-off="1">Download Excel</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="h5 mb-3">Other Group B segments</h3>
                    @php($segments = $assignments['group_b']['segments'] ?? [])
                    @if(empty($segments))
                    <p class="text-muted mb-0">No additional Group&nbsp;B segments met the export criteria (LATEST_BILL_MNY ≥ 5000).</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Segment</th>
                                    <th scope="col" class="text-end">Rows</th>
                                    <th scope="col" class="text-end">Download</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($segments as $segment)
                                <tr>
                                    <td>{{ $segment['name'] }}</td>
                                    <td class="text-end">{{ number_format($segment['count'] ?? 0) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => $segment['slug'] ?? 'segment']) }}" class="btn btn-outline-secondary btn-sm" data-loader-off="1">Download</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection