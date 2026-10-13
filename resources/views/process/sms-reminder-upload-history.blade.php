@extends('layouts.admin')

@section('title', 'SMS Reminder Upload History')

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
                        <h2 class="h5 mb-0">SMS Reminder Upload History</h2>
                        <p class="text-muted small mb-0">Review your recent SMS reminder uploads and track progress even after closing this page.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Back</a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="sms-history-refresh">Refresh</button>
                    </div>
                </div>
                <div id="sms-history-block">
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
    const historyBlock = document.getElementById('sms-history-block');
    const historyRefresh = document.getElementById('sms-history-refresh');

    let smsHistoryCurrentPage = 1;
    const smsHistoryPerPage = 10;

    function renderSmsHistoryPagination(meta) {
        const container = document.getElementById('sms-history-pagination');
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
                if (disabled || page === smsHistoryCurrentPage) return;
                fetchHistoryPage(page);
            });
            li.appendChild(a);
            return li;
        };

        container.appendChild(makeLi('Previous', Math.max(1, smsHistoryCurrentPage - 1), smsHistoryCurrentPage === 1));

        const maxButtons = 7;
        let start = Math.max(1, smsHistoryCurrentPage - Math.floor(maxButtons / 2));
        let end = Math.min(last, start + maxButtons - 1);
        if (end - start < maxButtons - 1) {
            start = Math.max(1, end - maxButtons + 1);
        }

        for (let p = start; p <= end; p++) {
            container.appendChild(makeLi(p, p, false, p === smsHistoryCurrentPage));
        }

        container.appendChild(makeLi('Next', Math.min(last, smsHistoryCurrentPage + 1), smsHistoryCurrentPage === last));
    }

    async function fetchHistoryPage(page = 1) {
        smsHistoryCurrentPage = page;
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', smsHistoryPerPage);

        const url = @json(route('sms_reminders.index')) + '?' + params.toString();
        try {
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) throw new Error('Network error');
            const data = await res.json();
            renderSmsHistoryTable(data.uploads || []);
            if (data.meta) {
                renderSmsHistoryPagination(data.meta);
            }
        } catch (err) {
            console.error('Failed to load SMS history page', err);
        }
    }

    function renderSmsHistoryTable(uploads) {
        if (!historyBlock) return;

        if (!uploads.length) {
            historyBlock.innerHTML = '<p class="text-muted mb-0">No SMS reminder uploads found.</p>';
            return;
        }

        const rows = uploads.map((upload) => {
            const date = new Date((upload.started_at || upload.created_at) * 1000).toLocaleString();
            const status = upload.status || 'processing';
            const progress = typeof upload.progress === 'number' ? upload.progress : 0;
            const message = upload.message || 'Processing…';
            const fileName = upload.original_name || 'Unknown file';
            const matched = typeof upload.matched === 'number' ? upload.matched : null;
            const updated = typeof upload.updated === 'number' ? upload.updated : null;
            const notFound = typeof upload.not_found === 'number' ? upload.not_found : null;
            const processedRows = typeof upload.processed_rows === 'number' ? upload.processed_rows : null;
            const totalRows = typeof upload.total_rows === 'number' ? upload.total_rows : null;

            let meta = '';
            if (processedRows !== null && totalRows !== null && totalRows > 0) {
                meta = `${processedRows} / ${totalRows} rows`;
            } else if (processedRows !== null) {
                meta = `${processedRows} rows`;
            }

            if (matched !== null && updated !== null && notFound !== null && status === 'complete') {
                meta = `Matched: ${matched} • Updated: ${updated} • Not found: ${notFound}`;
            }

            const statusBadge = status === 'complete'
                ? '<span class="badge bg-success">Complete</span>'
                : status === 'failed'
                    ? '<span class="badge bg-danger">Failed</span>'
                    : '<span class="badge bg-warning text-dark">Processing</span>';

            return `
                <tr>
                    <td>${fileName}</td>
                    <td>${date}</td>
                    <td>${statusBadge}</td>
                    <td>${progress}%</td>
                    <td>${updated !== null ? updated : '—'}</td>
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
                            <th>Updated</th>
                            <th>Message</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center mt-3">
                <nav aria-label="SMS reminder upload history pagination">
                    <ul class="pagination" id="sms-history-pagination"></ul>
                </nav>
            </div>
        `;
    }

    function loadHistory(page = 1) {
        if (!historyBlock) {
            return;
        }

        smsHistoryCurrentPage = page;
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', smsHistoryPerPage);

        const historyUrl = @json(route('sms_reminders.index')) + '?' + params.toString();

        fetch(historyUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then((response) => response.json())
        .then((data) => {
            renderSmsHistoryTable(data.uploads || []);
            if (data.meta) {
                renderSmsHistoryPagination(data.meta);
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
