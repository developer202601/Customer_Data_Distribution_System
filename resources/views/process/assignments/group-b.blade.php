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
@php($exportStatuses = $exports ?? [])
<div class="process-preview p-4 p-lg-5 shadow-sm">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">Enterprise, Wholesale &amp; SME Allocation</h1>
                <p class="text-muted mb-1">Enterprise, Wholesale, and SME segments with higher arrears thresholds are grouped into the downloads below.</p>
                <p class="text-muted mb-0">Dataset month: <strong>{{ $dataset['dataset_month'] ?? 'N/A' }}</strong></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('process.assignments.index') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                <a href="{{ route('process.assignments.group-a') }}" class="btn btn-outline-secondary" data-loader-off="1">Retail &amp; Micro exports</a>
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

        @php
            $assignmentLabels = $assignmentLabels ?? [];
            $resolveAssignment = function (?string $value) use ($assignmentLabels) {
                if ($value === null || $value === '') {
                    return '—';
                }

                $normalized = strtolower($value);

                foreach ($assignmentLabels as $key => $label) {
                    if ($normalized === strtolower($key)) {
                        return $label;
                    }
                }

                return ucfirst($value);
            };
            $segments = [
                'enterprise-wholesale' => [
                    'label' => $group['enterprise_wholesale']['label'] ?? 'Enterprise & Wholesale',
                    'count' => $group['enterprise_wholesale']['count'] ?? 0,
                    'description' => 'Includes business lines 47, 44, 41, and 76.',
                ],
                'sme' => [
                    'label' => $group['sme']['label'] ?? 'SME',
                    'count' => $group['sme']['count'] ?? 0,
                    'description' => 'Business line 31 grouped for SME operations.',
                ],
            ];
        @endphp

        <div class="group-section mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Download segments</h2>
                <span class="text-muted">{{ number_format(($group['enterprise_wholesale']['count'] ?? 0) + ($group['sme']['count'] ?? 0)) }} records available</span>
            </div>

            <div class="process-table-container mb-4">
                <div class="table-responsive">
                    <table class="table table-sm process-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Segment</th>
                                <th scope="col" class="text-end">Rows</th>
                                <th scope="col" class="text-end">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($segments as $bucket => $segment)
                            @php($bucketStatus = $exportStatuses[$bucket] ?? [])
                            @php($bucketState = $bucketStatus['status'] ?? 'processing')
                            @php($bucketReady = $bucketState === 'ready')
                            @php($bucketFailed = $bucketState === 'failed')
                            <tr>
                                <th scope="row">
                                    <div class="fw-semibold">{{ $segment['label'] }}</div>
                                    <div class="text-muted small">{{ $segment['description'] }}</div>
                                </th>
                                <td class="text-end">{{ number_format($segment['count']) }}</td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        @if($segment['count'] === 0)
                                        <span class="btn btn-outline-secondary disabled" aria-disabled="true">No export</span>
                                        @elseif($bucketFailed)
                                        <button type="button" class="btn btn-outline-danger" disabled>Generation failed</button>
                                        @elseif(! $bucketReady)
                                        <button type="button" class="btn btn-dark" disabled>Generating…</button>
                                        @else
                                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => $bucket]) }}" class="btn btn-dark" data-loader-off="1">Download</a>
                                        @endif

                                        @if($segment['count'] > 0)
                                        <a href="{{ route('process.assignments.download', ['group' => 'group-b', 'bucket' => $bucket, 'fresh' => 1]) }}" class="btn btn-outline-secondary" data-loader-off="1">Download live</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No enterprise, wholesale, or SME records available. Upload a dataset to continue.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="get" action="{{ route('process.assignments.group-b') }}" class="row g-2 align-items-end mb-4" data-loader-off="1">
                <div class="col-12 col-lg-6 col-xxl-4">
                    <label for="group-b-search" class="form-label">Search by customer reference, account number, product, or assignment</label>
                    <input type="text" class="form-control" id="group-b-search" name="search" value="{{ $search ?? '' }}" placeholder="e.g. 0112345678 or SME" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                @if(($search ?? '') !== '')
                <div class="col-auto">
                    <a href="{{ route('process.assignments.group-b') }}" class="btn btn-outline-secondary" data-loader-off="1">Clear</a>
                </div>
                @endif
            </form>

            <div class="process-table-container">
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
                            @forelse($rows as $row)
                            <tr>
                                <td>{{ $row->customer_ref ?? '—' }}</td>
                                <td>{{ $row->account_num ?? '—' }}</td>
                                <td>{{ $row->product_label ?? '—' }}</td>
                                <td>{{ $row->slt_business_line_value ?? '—' }}</td>
                                <td>{{ $resolveAssignment($row->assigned_to ?? null) }}</td>
                                <td class="text-end">{{ $row->new_arrears_value !== null ? number_format((float) $row->new_arrears_value, 2) : '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No records matched your filters.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($rows->hasPages())
            <div class="mt-3">
                {{ $rows->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection