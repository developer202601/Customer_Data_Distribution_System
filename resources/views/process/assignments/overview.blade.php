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

        // Debugging: Log the updated container content
        console.log('Updated container content:', container.innerHTML);
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
});
</script>
@endpush