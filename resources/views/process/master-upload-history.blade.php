@extends('layouts.admin')

@section('title', 'Master Upload History')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step active"></span>
    <span class="process-step"></span>
    <span class="process-step"></span>
</div>
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-0">Master Upload History</h2>
                        <p class="text-muted small mb-0">Review your recent master dataset uploads and track progress even after closing this page.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Back</a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="master-history-refresh">Refresh</button>
                    </div>
                </div>
                <div id="master-history-block">
                    <p class="text-muted mb-0">Loading upload history…</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function() {
    const historyBlock = document.getElementById('master-history-block');
    const historyRefresh = document.getElementById('master-history-refresh');

    let masterHistoryCurrentPage = 1;
    const masterHistoryPerPage = 10;

    function renderMasterHistoryPagination(meta) {
        const container = document.getElementById('master-history-pagination');
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
                if (disabled || page === masterHistoryCurrentPage) return;
                fetchHistoryPage(page);
            });
            li.appendChild(a);
            return li;
        };

        container.appendChild(makeLi('Previous', Math.max(1, masterHistoryCurrentPage - 1), masterHistoryCurrentPage === 1));

        const maxButtons = 7;
        let start = Math.max(1, masterHistoryCurrentPage - Math.floor(maxButtons / 2));
        let end = Math.min(last, start + maxButtons - 1);
        if (end - start < maxButtons - 1) {
            start = Math.max(1, end - maxButtons + 1);
        }

        for (let p = start; p <= end; p++) {
            container.appendChild(makeLi(p, p, false, p === masterHistoryCurrentPage));
        }

        container.appendChild(makeLi('Next', Math.min(last, masterHistoryCurrentPage + 1), masterHistoryCurrentPage === last));
    }

    async function fetchHistoryPage(page = 1) {
        masterHistoryCurrentPage = page;
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', masterHistoryPerPage);

        const url = @json(route('master.upload.history.json')) + '?' + params.toString();
        try {
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) throw new Error('Network error');
            const data = await res.json();
            renderHistoryTable(data.uploads || []);
            if (data.meta) {
                renderMasterHistoryPagination(data.meta);
            }
        } catch (err) {
            console.error('Failed to load history page', err);
        }
    }

    function renderHistoryTable(uploads) {
        if (!historyBlock) return;

        if (!uploads.length) {
            historyBlock.innerHTML = '<p class="text-muted mb-0">No master uploads found.</p>';
            return;
        }

        const rows = uploads.map((upload) => {
            const date = new Date((upload.started_at || upload.created_at) * 1000).toLocaleString();
            const status = upload.status || 'processing';
            const progress = typeof upload.progress === 'number' ? upload.progress : 0;
            const message = upload.message || 'Processing…';
            const fileName = upload.original_name || 'Unknown file';
            const processedRows = typeof upload.processed_rows === 'number' ? upload.processed_rows : null;
            const totalRows = typeof upload.total_rows === 'number' ? upload.total_rows : null;
            const fileSize = typeof upload.file_size === 'number' ? upload.file_size : null;

            let meta = '';
            if (processedRows !== null && totalRows !== null && totalRows > 0) {
                meta = `${processedRows} / ${totalRows} rows`;
            } else if (processedRows !== null) {
                meta = `${processedRows} rows`;
            }

            if (fileSize !== null) {
                meta = meta ? `${meta} • ` : '';
                const units = ['B', 'KB', 'MB', 'GB'];
                let index = 0;
                let value = fileSize;
                while (value >= 1024 && index < units.length - 1) {
                    value /= 1024;
                    index += 1;
                }
                meta += `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
            }

            const statusBadge = status === 'submitted' || status === 'complete'
                ? '<span class="badge bg-success">Complete</span>'
                : status === 'failed' || status === 'canceled'
                    ? '<span class="badge bg-danger">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>'
                    : '<span class="badge bg-warning text-dark">Processing</span>';

            return `
                <tr>
                    <td>${fileName}</td>
                    <td>${date}</td>
                    <td>${statusBadge}</td>
                    <td>${progress}%</td>
                    <td>${message}</td>
                    <td>${meta || '—'}</td>
                </tr>
            `;
        }).join('');

        historyBlock.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Started</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Message</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center mt-3">
                <nav aria-label="Master upload history pagination">
                    <ul class="pagination" id="master-history-pagination"></ul>
                </nav>
            </div>
        `;
    }

    function loadHistory(page = 1) {
        if (!historyBlock) {
            return;
        }

        masterHistoryCurrentPage = page;
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', masterHistoryPerPage);

        const historyUrl = @json(route('master.upload.history.json')) + '?' + params.toString();

        fetch(historyUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then((response) => response.json())
        .then((data) => {
            renderHistoryTable(data.uploads || []);
            if (data.meta) {
                renderMasterHistoryPagination(data.meta);
            }
        })
        .catch(() => {
            historyBlock.innerHTML = '<p class="text-danger mb-0">Failed to load upload history.</p>';
        });
    }

    if (historyRefresh) {
        historyRefresh.addEventListener('click', () => loadHistory(1));
    }

    if (historyBlock) {
        loadHistory(1);
    }
})();
</script>
@endpush
