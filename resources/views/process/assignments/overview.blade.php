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
                <p class="text-muted mb-1">Choose which allocation set you want to review and download. Retail &amp; Micro quotas sit alongside Enterprise, Wholesale, and SME segments.</p>
                <p class="text-muted mb-0">Dataset month: <strong>{{ $dataset['dataset_month'] ?? 'N/A' }}</strong> · Total rows: {{ number_format($dataset['row_count'] ?? 0) }} · Excluded: {{ number_format($dataset['excluded_count'] ?? 0) }}</p>
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

        @php
            $exportStatuses = $exports ?? [];
            $groupAQuotas = $groupA['quotas'] ?? [];
            $regionCount = $groupA['region']['actual'] ?? 0;
            $renderDownloadButtons = function (string $groupKey, string $bucket, int $count) use ($exportStatuses) {
                $status = $exportStatuses[$bucket] ?? [];
                $state = $status['status'] ?? 'processing';
                $ready = $state === 'ready';
                $failed = $state === 'failed';

                echo '<div class="d-flex flex-wrap justify-content-end gap-2">';

                if ($count === 0) {
                    echo '<span class="btn btn-outline-secondary disabled" aria-disabled="true">No export</span>';
                } elseif ($failed) {
                    echo '<button type="button" class="btn btn-outline-danger" disabled>Generation failed</button>';
                } elseif (! $ready) {
                    echo '<button type="button" class="btn btn-dark" disabled>Generating…</button>';
                } else {
                    echo '<a href="' . route('process.assignments.download', ['group' => $groupKey, 'bucket' => $bucket]) . '" class="btn btn-dark" data-loader-off="1">Download</a>';
                }

                if ($count > 0) {
                    echo '<a href="' . route('process.assignments.download', ['group' => $groupKey, 'bucket' => $bucket, 'fresh' => 1]) . '" class="btn btn-outline-secondary" data-loader-off="1">Download live</a>';
                }

                echo '</div>';
            };
        @endphp

        <div class="mb-5">
            <h2 class="h4 mb-3">Retail &amp; Micro</h2>
            <div class="process-table-container">
                <div class="table-responsive">
                    <table class="table table-sm process-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="align-middle">Segment</th>
                                <th scope="col" class="text-end align-middle">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groupAQuotas as $key => $quota)
                            <tr>
                                <th scope="row">
                                    @php $quotaCount = (int) ($quota['actual'] ?? 0); @endphp
                                    <div class="fw-semibold mb-0">
                                        {{ $quota['label'] ?? 'Quota' }}
                                        <span class="text-muted">({{ number_format($quotaCount) }})</span>
                                    </div>
                                </th>
                                <td class="text-end align-middle">
                                    @php $renderDownloadButtons('group-a', $key, $quotaCount); @endphp
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted">No retail &amp; micro quotas available. Upload a dataset to continue.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mb-5">
            <h2 class="h4 mb-3">Region Billing Centre</h2>
            <div class="process-table-container">
                <div class="table-responsive">
                    <table class="table table-sm process-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="align-middle">Segment</th>
                                <th scope="col" class="text-end align-middle">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <div class="fw-semibold mb-0">
                                        Region Billing Centre
                                        <span class="text-muted">({{ number_format($regionCount) }})</span>
                                    </div>
                                </th>
                                <td class="text-end align-middle">
                                    @php $renderDownloadButtons('region', 'region-billing', (int) $regionCount); @endphp
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mb-5">
            <h2 class="h4 mb-3">Enterprise, Wholesale &amp; SME</h2>
            <div class="process-table-container">
                <div class="table-responsive">
                    <table class="table table-sm process-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="align-middle">Segment</th>
                                <th scope="col" class="text-end align-middle">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $enterpriseCount = (int) ($groupB['enterprise_wholesale']['count'] ?? 0);
                                $smeCount = (int) ($groupB['sme']['count'] ?? 0);
                            @endphp
                            <tr>
                                <th scope="row">
                                    <div class="fw-semibold mb-0">
                                        Enterprise &amp; Wholesale
                                        <span class="text-muted">({{ number_format($enterpriseCount) }})</span>
                                    </div>
                                </th>
                                <td class="text-end align-middle">
                                    @php $renderDownloadButtons('group-b', 'enterprise-wholesale', $enterpriseCount); @endphp
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <div class="fw-semibold mb-0">
                                        SME
                                        <span class="text-muted">({{ number_format($smeCount) }})</span>
                                    </div>
                                </th>
                                <td class="text-end align-middle">
                                    @php $renderDownloadButtons('group-b', 'sme', $smeCount); @endphp
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mb-5">
            <h2 class="h4 mb-3">VIP Records</h2>
            <div class="process-table-container">
                <div class="table-responsive">
                    <table class="table table-sm process-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="align-middle">Segment</th>
                                <th scope="col" class="text-end align-middle">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $vipCount = (int) ($vip['count'] ?? 0); @endphp
                            <tr>
                                <th scope="row">
                                    <div class="fw-semibold mb-0">
                                        VIP Records
                                        <span class="text-muted">({{ number_format($vipCount) }})</span>
                                    </div>
                                </th>
                                <td class="text-end align-middle">
                                    @php $renderDownloadButtons('vip', 'vip', $vipCount); @endphp
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <h2 class="h4 mb-3">Exclusions</h2>
            <div class="process-table-container">
                <div class="table-responsive">
                    <table class="table table-sm process-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="align-middle">Segment</th>
                                <th scope="col" class="text-end align-middle">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $excludedCount = (int) ($exclusions['total_excluded'] ?? 0); @endphp
                            <tr>
                                <th scope="row">
                                    <div class="fw-semibold mb-0">
                                        Excluded Records
                                        <span class="text-muted">({{ number_format($excludedCount) }})</span>
                                    </div>
                                </th>
                                <td class="text-end align-middle">
                                    @php $renderDownloadButtons('exclusions', 'excluded', $excludedCount); @endphp
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @php
            $assignmentLabels = $assignmentLabels ?? [];
            $resolveAssignment = function ($row) use ($assignmentLabels) {
                if ($row->excluded) {
                    return 'Excluded';
                }

                $value = $row->assigned_to;

                if ($value === null || $value === '') {
                    return 'Unassigned';
                }

                foreach ($assignmentLabels as $key => $label) {
                    if (strtolower($value) === strtolower($key)) {
                        return $label;
                    }
                }

                return ucfirst($value);
            };
        @endphp

        <div class="mt-5">
            <form method="get" action="{{ route('process.assignments.index') }}" class="row g-2 align-items-end" data-loader-off="1">
                <div class="col-12 col-lg-6 col-xxl-4">
                    <label for="overview-search" class="form-label">Search across all records</label>
                    <input type="text" class="form-control" id="overview-search" name="search" value="{{ $search ?? '' }}" placeholder="e.g. customer reference, account, product, or assignment" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                @if(! empty($search))
                <div class="col-auto">
                    <a href="{{ route('process.assignments.index') }}" class="btn btn-outline-secondary" data-loader-off="1">Clear</a>
                </div>
                @endif
            </form>

            @if(! empty($search))
                @php
                    $resultItems = isset($rows) ? $rows->items() : [];
                @endphp
                <div class="process-table-container mt-4">
                    <div class="table-responsive">
                        <table class="table table-striped process-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Customer Reference</th>
                                    <th scope="col">Account Number</th>
                                    <th scope="col">Product Label</th>
                                    <th scope="col">Business Line</th>
                                    <th scope="col">Assignment</th>
                                    <th scope="col" class="text-end">New Arrears (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($resultItems as $row)
                                <tr>
                                    <td>{{ $row->customer_ref ?? '—' }}</td>
                                    <td>{{ $row->account_num ?? '—' }}</td>
                                    <td>{{ $row->product_label ?? '—' }}</td>
                                    <td>{{ $row->slt_business_line_value ?? '—' }}</td>
                                    <td>{{ $resolveAssignment($row) }}</td>
                                    <td class="text-end">{{ $row->new_arrears_value !== null ? number_format((float) $row->new_arrears_value, 2) : '—' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No records matched your search.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if(isset($rows) && $rows->hasPages())
                <div class="mt-3">
                    {{ $rows->links('pagination::bootstrap-5') }}
                </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection