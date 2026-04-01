@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
</div>
@if(session('user.is_admin'))
<a href="{{ route('admin.config') }}" class="btn btn-outline-secondary">Configurations</a>
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
            <div class="d-flex flex-wrap gap-2">
                <a href="#" class="btn btn-outline-secondary" data-loader-off="1" onclick="history.back(); return false;">Back</a>
            </div>
        </div>

        @php
            $configSource = $process->assignment_config_source;
            $configOverrides = is_array($process->assignment_config_overrides) ? $process->assignment_config_overrides : null;
            $defaultSnapshot = is_array($process->assignment_config_default_snapshot) ? $process->assignment_config_default_snapshot : null;
            $defaultConfig = is_array($assignmentConfigDefault ?? null) ? $assignmentConfigDefault : null;
            $configSetter = $process->assignmentConfigSetter;
            $configSetterLabel = null;
            if ($configSetter) {
                $configSetterLabel = $configSetter->username ?: ($configSetter->name ?: ('User #' . $configSetter->id));
            }

            $usedConfig = $configOverrides;
            $defaultForCompare = $defaultSnapshot ?: $defaultConfig;
            $configRows = [
                'lower_range' => ['label' => 'Lower range (LKR)'],
                'upper_range' => ['label' => 'Upper range (LKR)'],
                'call_center_staff_quota' => ['label' => 'Call Center Staff quota'],
                'call_center_quota' => ['label' => 'Call Center quota'],
                'staff_quota' => ['label' => 'Staff quota'],
            ];
        @endphp

        <div class="card mb-4 shadow-sm" style="border-radius:1rem;">
            <div class="card-body p-4 p-lg-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h5 mb-1">Assignment configuration</h2>
                        <div class="text-muted">
                            @if($configSource === 'manual')
                                <span class="badge text-bg-warning">Manual</span>
                            @elseif($configSource === 'default')
                                <span class="badge text-bg-secondary">Default</span>
                            @else
                                <span class="badge text-bg-light">Unknown</span>
                            @endif
                            @if($process->assignment_config_set_at)
                                <span class="ms-2">Set at {{ $process->assignment_config_set_at->format('Y-m-d H:i') }}</span>
                            @endif
                        </div>
                        @if($process->assignment_config_set_by_user_id)
                            <div class="text-muted">Set by: {{ $configSetterLabel ?? ('User #' . $process->assignment_config_set_by_user_id) }} (ID {{ $process->assignment_config_set_by_user_id }})</div>
                        @endif
                    </div>
                    <div class="text-muted small">
                        Applied counts: Call Center Staff {{ number_format((int) $process->call_center_staff_count) }}, Call Center {{ number_format((int) $process->call_center_count) }}, Staff {{ number_format((int) $process->staff_count) }}
                        @if($process->assignment_config_ftth_count !== null)
                            <div>FTTH records (Retail/Micro, post-exclusion): {{ number_format((int) $process->assignment_config_ftth_count) }}</div>
                        @endif
                    </div>
                </div>

                @if($usedConfig)
                    <div class="table-responsive mt-3">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 240px;">Field</th>
                                    <th>Default (at run time)</th>
                                    <th>Used for this process</th>
                                    <th style="width: 110px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($configRows as $key => $meta)
                                    @php
                                        $defaultVal = $defaultForCompare[$key] ?? null;
                                        $usedVal = $usedConfig[$key] ?? null;
                                        $changed = $defaultForCompare ? ((string) (int) $defaultVal !== (string) (int) $usedVal) : null;
                                    @endphp
                                    <tr>
                                        <th>{{ $meta['label'] }}</th>
                                        <td>{{ $defaultVal === null ? '—' : number_format((int) $defaultVal) }}</td>
                                        <td>{{ $usedVal === null ? '—' : number_format((int) $usedVal) }}</td>
                                        <td>
                                            @if($changed === null)
                                                <span class="badge text-bg-light">Unknown</span>
                                            @elseif($changed)
                                                <span class="badge text-bg-warning">Changed</span>
                                            @else
                                                <span class="badge text-bg-secondary">Default</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($defaultSnapshot && $defaultConfig && $defaultSnapshot !== $defaultConfig)
                        <div class="text-muted small mt-2">
                            Note: admin defaults were changed after this process ran, so "Default (at run time)" may differ from current configuration.
                        </div>
                    @endif
                @else
                    <div class="text-muted mt-3">No configuration audit data recorded for this dataset.</div>
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
                    $csvUrl = route('process.assignments.download', ['group' => $groupKey, 'bucket' => $bucket]) . '?format=csv';
                    $xlsxUrl = route('process.assignments.download', ['group' => $groupKey, 'bucket' => $bucket]) . '?format=xlsx';
                    echo '<a href="' . $csvUrl . '" class="btn btn-outline-secondary" data-loader-off="1">Download CSV</a>';
                    echo '<a href="' . $xlsxUrl . '" class="btn btn-dark" data-loader-off="1">Download XLSX</a>';
                }

                // Removed live download option per new requirement.

                echo '</div>';
            };
        @endphp

        <div class="mb-5">
            <h2 class="h4 mb-3">Retail &amp; Micro Business</h2>
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
                            @php
                                $excludedCount = (int) ($exclusions['total_excluded'] ?? 0);
                                $retailMicroCopperCount = (int) ($exclusions['retail_micro_copper_count'] ?? 0);
                            @endphp
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
                            <tr>
                                <th scope="row">
                                    <div class="fw-semibold mb-0">
                                        Copper - Retail &amp; Micro Business
                                        <span class="text-muted">({{ number_format($retailMicroCopperCount) }})</span>
                                    </div>
                                </th>
                                <td class="text-end align-middle">
                                    @php $renderDownloadButtons('exclusions', 'excluded-copper-retail-micro', $retailMicroCopperCount); @endphp
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
            <form id="overview-search-form" method="get" action="{{ route('process.assignments.index') }}" class="row g-2 align-items-end" data-loader-off="1">
                <div class="col-12 col-lg-6 col-xxl-4">
                    <label for="overview-search" class="form-label">Search by account number</label>
                    <input type="text" class="form-control" id="overview-search" name="search" value="{{ $search ?? '' }}" placeholder="Enter account number" autocomplete="off">
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
                <div id="overview-results">
                    @include('process.assignments.partials.overview-results', [
                        'rows' => $rows ?? null,
                        'assignmentLabels' => $assignmentLabels ?? [],
                    ])
                </div>
            @endif
        </div>

        <div id="overview-results">
            <div id="loading-indicator" style="display: none; text-align: center; margin: 20px 0;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <!-- Table content will be dynamically loaded here -->
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
    console.log('JavaScript loaded and DOMContentLoaded event fired.');

    const container = document.getElementById('overview-results');
    const form = document.getElementById('overview-search-form');
    const loadingIndicator = document.getElementById('loading-indicator');
    const exportStatusUrl = @json(route('process.assignments.exports.status'));
    let exportButtonBlocks = Array.from(document.querySelectorAll('[data-export-buttons]'));

    if (!container) {
        console.error('Container #overview-results not found.');
        return;
    }

    if (!form) {
        console.error('Form #overview-search-form not found.');
        return;
    }

    if (!loadingIndicator) {
        console.error('Loading indicator #loading-indicator not found.');
        return;
    }

    console.log('Event listeners are being attached.');

    const refreshExportBlocks = () => {
        exportButtonBlocks = Array.from(document.querySelectorAll('[data-export-buttons]'));
    };

    const renderButtons = (target, state) => {
        const count = Number(target.dataset.exportCount || '0');
        const group = target.dataset.exportGroup;
        const bucket = target.dataset.exportBucket;
        const csvUrl = `/process/assignments/download/${group}/${bucket}?format=csv`;
        const xlsxUrl = `/process/assignments/download/${group}/${bucket}?format=xlsx`;

        if (count === 0) {
            target.innerHTML = '<span class="btn btn-outline-secondary disabled" aria-disabled="true">No export</span>';
            return;
        }

        if (state === 'failed') {
            target.innerHTML = '<button type="button" class="btn btn-outline-danger" disabled>Generation failed</button>';
            return;
        }

        if (state !== 'ready') {
            target.innerHTML = '<button type="button" class="btn btn-dark" disabled>Generating…</button>';
            return;
        }

        target.innerHTML =
            '<a href="' + csvUrl + '" class="btn btn-outline-secondary" data-loader-off="1">Download CSV</a>' +
            '<a href="' + xlsxUrl + '" class="btn btn-dark" data-loader-off="1">Download XLSX</a>';
    };

    const pollExportStatus = () => {
        if (exportButtonBlocks.length === 0) return;

        fetch(exportStatusUrl, {
            headers: {
                'Accept': 'application/json',
            },
            cache: 'no-store',
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((payload) => {
                if (!payload || !payload.exports) return;

                exportButtonBlocks.forEach((block) => {
                    const bucket = block.dataset.exportBucket;
                    const status = payload.exports[bucket] || {};
                    const state = status.status || 'processing';
                    renderButtons(block, state);
                });
            })
            .catch(() => {});
    };

    const fetchAndReplace = (url) => {
        const target = new URL(url, window.location.origin);
        console.log('Initiating AJAX request to:', target.toString());

        loadingIndicator.style.display = 'block';

        fetch(target.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html',
            },
            cache: 'no-store',
            credentials: 'same-origin',
        })
            .then((response) => {
                console.log('AJAX response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then((html) => {
                console.log('AJAX request successful. Updating container content.');
                container.innerHTML = html;
                window.history.replaceState({}, '', target.toString());
                container.scrollIntoView({ behavior: 'smooth' });
                refreshExportBlocks();
            })
            .catch((error) => {
                console.error('Error during AJAX request:', error);
            })
            .finally(() => {
                console.log('Hiding loading indicator.');
                loadingIndicator.style.display = 'none';
            });
    };

    container.addEventListener('click', (e) => {
        const link = e.target.closest('a.page-link');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#')) return;
        e.preventDefault();
        console.log('Pagination link clicked:', href);
        fetchAndReplace(href);
    });

    form.addEventListener('submit', (e) => {
        console.log('Form submission intercepted.');
        e.preventDefault();

        const data = new FormData(form);
        const params = new URLSearchParams(data);
        const url = form.action + '?' + params.toString();

        console.log('Form submitted. AJAX request URL:', url);
        fetchAndReplace(url);
    });

    pollExportStatus();
    window.setInterval(pollExportStatus, 3000);
});
</script>
@endpush