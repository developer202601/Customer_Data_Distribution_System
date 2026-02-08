@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step active"></span>
    <span class="process-step"></span>
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
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">{{ $vipApplied ? 'VIP Records' : 'Filtered Results' }}</h1>
                <p class="text-muted mb-1">
                    @if($vipApplied)
                    The list below contains the subset of filtered rows where <strong>CREDIT_CLASS_NAME</strong> starts with <strong>VIP</strong> (for example, "VIP" or "VIP - Low").
                    @else
                    The dataset has been filtered to include only records where the medium is <strong>Copper</strong> or <strong>FTTH</strong>, the latest product status is <strong>OK</strong>, Invoicing CO ID is equals to <strong>1</strong> and the arrears value is greater than <strong>2400</strong>.
                    @endif
                </p>
                @if($filename)
                <p class="text-muted mb-0">Source file: <strong>{{ $filename }}</strong></p>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ $vipApplied ? route('process.assignments.index') : route('master.upload.create') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                <!-- @if(! $vipApplied)
                <a href="{{ route('process.exclusions.create') }}" class="btn btn-outline-secondary" data-loader-off="1">Upload exclusion files</a>
                @endif -->
                @if($vipApplied)
                <a href="{{ route('process.upload.export', array_filter(['vip' => 1, 'search' => $searchTerm ?: null])) }}" class="btn btn-dark" data-loader-off="1" target="_blank" rel="noopener noreferrer">Export VIP Excel</a>
                @else
                <a href="{{ route('process.upload.vip', array_filter(['search' => $searchTerm ?: null])) }}" class="btn btn-dark">VIP records</a>
                @endif
            </div>
        </div>

        @include('partials.process-toast', ['title' => 'Upload complete'])

        <div class="row g-3 process-summary-row mb-4">
            <div class="col-md-4">
                <div class="process-summary-card">
                    <span class="process-summary-label">Matching rows</span>
                    <span class="process-summary-value">{{ number_format($summary['filtered_rows']) }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="process-summary-card">
                    <span class="process-summary-label">Rows evaluated</span>
                    <span class="process-summary-value">{{ number_format($summary['total_rows']) }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="process-summary-card">
                    <span class="process-summary-label">Filtered out</span>
                    <span class="process-summary-value">{{ number_format($summary['skipped_rows']) }}</span>
                </div>
            </div>
        </div>

        @if($searchApplied)
        <p id="process-count-text" class="text-muted mb-4">
            Showing {{ number_format($displayCount) }} matching {{ \Illuminate\Support\Str::plural('row', $displayCount) }} for “{{ $searchTerm }}”.
        </p>
        @elseif($limited)
        <p id="process-count-text" class="text-muted mb-4">
            Showing the first 10 of {{ number_format($filteredCount) }} rows. Use the search to locate additional records.
        </p>
        @else
        <p id="process-count-text" class="text-muted mb-4">
            Showing {{ number_format($filteredCount) }} {{ \Illuminate\Support\Str::plural('row', $filteredCount) }}.
        </p>
        @endif

        <form method="get" class="process-search mb-4" data-loader-off>
            @if($vipApplied)
            <input type="hidden" name="vip" value="1">
            @endif
            <div class="input-group">
                <input type="search" name="search" class="form-control" placeholder="Search by customer reference, account number, or product label" value="{{ $searchTerm }}">
                <button type="submit" class="btn btn-dark">Search</button>
            </div>
            <small class="form-text text-muted">Search is case-insensitive and matches partial values.</small>
        </form>

        @if(empty($filteredRows))
        <div class="alert alert-info" role="alert">
            @if($searchApplied && $vipApplied)
            No VIP records matched your search. Try a different customer reference, account number, or product label.
            @elseif($searchApplied)
            No records matched your search. Try a different customer reference, account number, or product label.
            @elseif($vipApplied)
            No VIP records matched the filter criteria. Upload another file or adjust the source data before trying again.
            @else
            No records matched the filter criteria. Upload another file or adjust the source data before trying again.
            @endif
        </div>
        @else
        <div class="process-table-container">
            <div class="table-responsive position-relative">
                <table class="table table-sm table-striped process-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Excel Row</th>
                            @foreach($headers as $meta)
                            <th scope="col">{{ $meta['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="process-table-body">
                        @foreach($filteredRows as $rowIndex => $row)
                        <tr>
                            <td>{{ $rowIndex }}</td>
                            @foreach($headers as $letter => $meta)
                            <td>{{ $row[$letter] ?? '' }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div id="process-table-overlay" class="process-table-overlay position-absolute top-0 start-0 w-100 h-100 d-none">
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center text-white">
                            <div class="spinner-border text-light" role="status" aria-hidden="true"></div>
                            <div class="mt-2"><strong>Loading…</strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center mt-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination" id="process-pagination"></ul>
                </nav>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
    const routeRows = "{{ route('process.upload.rows') }}";
    const headersOrder = @json(array_keys($headers));
    const headerLabels = @json(array_map(fn($m) => $m['label'], $headers));
    let currentPage = 1;
    const perPage = 100;
    const vip = {{ $vipApplied ? 'true' : 'false' }};
    const initialSearch = "{{ addslashes($searchTerm) }}";

    function buildRowHtml(row) {
        let html = '<tr>';
        html += '<td>' + (row.excel_row ?? '') + '</td>';
        for (const key of headersOrder) {
            const val = row[key] ?? '';
            html += '<td>' + String(val).replace(/</g, '&lt;') + '</td>';
        }
        html += '</tr>';
        return html;
    }

    function renderPagination(meta) {
        const container = document.getElementById('process-pagination');
        if (!container) return;
        container.innerHTML = '';
        const total = meta.total || 0;
        const last = meta.last_page || 1;
        if (last <= 1) return;

        const makeLi = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.dataset.page = page;
            a.textContent = label;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                if (disabled || page === currentPage) return;
                fetchPage(page);
            });
            li.appendChild(a);
            return li;
        };

        container.appendChild(makeLi('Previous', Math.max(1, currentPage - 1), currentPage === 1));

        const maxButtons = 7;
        let start = Math.max(1, currentPage - Math.floor(maxButtons / 2));
        let end = Math.min(last, start + maxButtons - 1);
        if (end - start < maxButtons - 1) {
            start = Math.max(1, end - maxButtons + 1);
        }

        for (let p = start; p <= end; p++) {
            container.appendChild(makeLi(p, p, false, p === currentPage));
        }

        container.appendChild(makeLi('Next', Math.min(last, currentPage + 1), currentPage === last));
    }

    function updateCountText(meta) {
        const el = document.getElementById('process-count-text');
        if (!el) return;
        if (initialSearch && initialSearch.length > 0) {
            el.textContent = `Showing ${meta.per_page} matching ${meta.total} for "${initialSearch}".`;
            return;
        }
        if ({{ $limited ? 'true' : 'false' }}) {
            el.textContent = `Showing the first ${meta.per_page} of ${meta.total} rows. Use the search to locate additional records.`;
            return;
        }
        el.textContent = `Showing ${meta.total} row${meta.total === 1 ? '' : 's'}.`;
    }

    async function fetchPage(page = 1) {
        currentPage = page;
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', perPage);
        if (vip) params.set('vip', 1);
        const searchInput = document.querySelector('input[name="search"]');
        const searchVal = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
        if (searchVal) params.set('search', searchVal);

        const url = routeRows + '?' + params.toString();
        try {
            const tbody = document.getElementById('process-table-body');
            const overlay = document.getElementById('process-table-overlay');
            if (overlay) overlay.classList.remove('d-none');

            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Network error');
            const data = await res.json();

            if (overlay) overlay.classList.add('d-none');
            if (!tbody) return;
            tbody.innerHTML = '';
            if (!data.rows.length) {
                const colspan = Math.max(1, (headersOrder.length || 0) + 1);
                tbody.innerHTML = `<tr class="table-empty"><td colspan="${colspan}" class="process-table-empty-message">No records matched your filters.</td></tr>`;
            } else {
                for (const row of data.rows) {
                    tbody.insertAdjacentHTML('beforeend', buildRowHtml(row));
                }
            }
            renderPagination(data.meta);
            updateCountText({ total: data.meta.total, per_page: data.meta.per_page });
        } catch (err) {
            console.error('Failed to load page', err);
            const overlay = document.getElementById('process-table-overlay');
            if (overlay) overlay.classList.add('d-none');
            const tbody = document.getElementById('process-table-body');
            if (tbody) {
                const colspan = Math.max(1, (headersOrder.length || 0) + 1);
                tbody.innerHTML = `<tr class="table-error"><td colspan="${colspan}" class="text-center text-danger py-3">Failed to load data. Please try again.</td></tr>`;
            }
        }
    }

    // wire search form to fetch page 1
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form.process-search');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                fetchPage(1);
            });
        }

        // initial pagination render if needed
        fetchPage(1);
    });
})();
</script>
@endpush