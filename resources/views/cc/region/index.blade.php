@extends('layouts.cc')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center — Region: {{ $region }}</p>
                        <h1 class="process-upload-title mb-0">RTOMs & RTOM Admins</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.region.create_admin') }}" class="btn btn-outline-success rounded-pill px-4">Add RTOM Admin</a>
                    </div>
                </div>

                {{-- status popup shown elsewhere; removed inline alert --}}

                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 mb-0">RTOM Admins</h2>
                            <form method="get" action="{{ route('cc.region.index') }}" class="d-flex gap-2">
                                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search username or name" value="{{ old('q', $q ?? request('q')) }}">
                                <select name="rtom" class="form-select form-select-sm">
                                    <option value="">All RTOMs</option>
                                    @foreach(($rtoms ?? collect()) as $r)
                                        <option value="{{ $r }}" {{ (string)($selectedRtom ?? request('rtom')) === (string)$r ? 'selected' : '' }}>{{ $r }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>

                        <div class="table-responsive cc-table-container">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Assignment</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cc-rtom-rows">
                                    @include('cc.region._rows', ['rtomAdmins' => $rtomAdmins])
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
                    <!-- Disable confirmation modal -->
                    <div class="modal fade" id="ccDisableConfirmModal" tabindex="-1" aria-labelledby="ccDisableConfirmLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="ccDisableConfirmLabel">Disable User</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to change status for the following user?</p>
                                    <p class="mb-0"><strong>Username:</strong> <span id="ccDisableConfirmUsername">—</span></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" id="ccDisableConfirm" class="btn btn-warning rounded-pill px-4">Disable</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delete confirmation modal -->
                    <div class="modal fade" id="ccDeleteConfirmModal" tabindex="-1" aria-labelledby="ccDeleteConfirmLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="ccDeleteConfirmLabel">Delete User</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>This action will permanently delete the user. This cannot be undone. Continue?</p>
                                    <p class="mb-0"><strong>Username:</strong> <span id="ccDeleteConfirmUsername">—</span></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" id="ccDeleteConfirm" class="btn btn-danger rounded-pill px-4">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden forms used by JS to submit disable/delete actions -->
                    <form id="cc-disable-form" method="post" style="display:none">@csrf @method('put')</form>
                    <form id="cc-enable-form" method="post" style="display:none">@csrf @method('put')</form>
                    <form id="cc-delete-form" method="post" style="display:none">@csrf @method('delete')</form>

                @push('scripts')
                <script nonce="{{ $cspNonce ?? '' }}">
                document.addEventListener('DOMContentLoaded', function () {
                    const disableUsernameEl = document.getElementById('ccDisableConfirmUsername');
                    const deleteUsernameEl = document.getElementById('ccDeleteConfirmUsername');
                    const disableConfirmBtn = document.getElementById('ccDisableConfirm');
                    const deleteConfirmBtn = document.getElementById('ccDeleteConfirm');
                    const disableForm = document.getElementById('cc-disable-form');
                    const deleteForm = document.getElementById('cc-delete-form');
                    const enableForm = document.getElementById('cc-enable-form');

                    let pendingAction = null;
                    let disableModal = null;
                    let deleteModal = null;
                    const disableModalEl = document.getElementById('ccDisableConfirmModal');
                    const deleteModalEl = document.getElementById('ccDeleteConfirmModal');
                    if (window.bootstrap && disableModalEl) disableModal = new bootstrap.Modal(disableModalEl);
                    if (window.bootstrap && deleteModalEl) deleteModal = new bootstrap.Modal(deleteModalEl);

                    document.querySelectorAll('.cc-disable-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            pendingAction = btn.getAttribute('data-action');
                            const uname = btn.getAttribute('data-username') || '—';
                            if (disableUsernameEl) disableUsernameEl.textContent = uname;
                            if (disableConfirmBtn) disableConfirmBtn.textContent = 'Disable';
                            disableModal?.show();
                        });
                    });

                    document.querySelectorAll('.cc-enable-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            pendingAction = btn.getAttribute('data-action');
                            const uname = btn.getAttribute('data-username') || '—';
                            if (disableUsernameEl) disableUsernameEl.textContent = uname;
                            if (disableConfirmBtn) disableConfirmBtn.textContent = 'Enable';
                            disableModal?.show();
                        });
                    });

                    document.querySelectorAll('.cc-delete-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            pendingAction = btn.getAttribute('data-action');
                            const uname = btn.getAttribute('data-username') || '—';
                            if (deleteUsernameEl) deleteUsernameEl.textContent = uname;
                            deleteModal?.show();
                        });
                    });

                    if (disableConfirmBtn) {
                        disableConfirmBtn.addEventListener('click', () => {
                            if (!pendingAction) return;
                            if (pendingAction.indexOf('/enable') !== -1 && enableForm) {
                                enableForm.action = pendingAction;
                                disableModal?.hide();
                                enableForm.submit();
                            } else if (disableForm) {
                                disableForm.action = pendingAction;
                                disableModal?.hide();
                                disableForm.submit();
                            }
                        });
                    }

                    if (deleteConfirmBtn) {
                        deleteConfirmBtn.addEventListener('click', () => {
                            if (!pendingAction) return;
                            deleteForm.action = pendingAction;
                            deleteModal?.hide();
                            deleteForm.submit();
                        });
                    }
                    // Instant search/filter: debounce input and fetch rows via AJAX
                    const searchInput = document.querySelector('input[name="q"]');
                    const rtomSelect = document.querySelector('select[name="rtom"]');
                    const rowsTbody = document.getElementById('cc-rtom-rows');
                    let tick = null;

                    function bindRowActions() {
                        // Re-bind action buttons (called after AJAX content replacement)
                        document.querySelectorAll('.cc-disable-btn').forEach(btn => {
                            btn.onclick = () => {
                                pendingAction = btn.getAttribute('data-action');
                                const uname = btn.getAttribute('data-username') || '—';
                                if (disableUsernameEl) disableUsernameEl.textContent = uname;
                                if (disableConfirmBtn) disableConfirmBtn.textContent = 'Disable';
                                disableModal?.show();
                            };
                        });

                        document.querySelectorAll('.cc-enable-btn').forEach(btn => {
                            btn.onclick = () => {
                                pendingAction = btn.getAttribute('data-action');
                                const uname = btn.getAttribute('data-username') || '—';
                                if (disableUsernameEl) disableUsernameEl.textContent = uname;
                                if (disableConfirmBtn) disableConfirmBtn.textContent = 'Enable';
                                disableModal?.show();
                            };
                        });

                        document.querySelectorAll('.cc-delete-btn').forEach(btn => {
                            btn.onclick = () => {
                                pendingAction = btn.getAttribute('data-action');
                                const uname = btn.getAttribute('data-username') || '—';
                                if (deleteUsernameEl) deleteUsernameEl.textContent = uname;
                                deleteModal?.show();
                            };
                        });
                    }

                    function fetchRows() {
                        const q = searchInput ? searchInput.value.trim() : '';
                        const rtom = rtomSelect ? rtomSelect.value : '';
                        const params = new URLSearchParams();
                        if (q) params.set('q', q);
                        if (rtom) params.set('rtom', rtom);

                        // show inline loading row
                        if (rowsTbody) {
                            rowsTbody.innerHTML = '<tr id="cc-rtom-loading"><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>Loading…</td></tr>';
                        }

                        fetch("{{ route('cc.region.search') }}?" + params.toString(), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }).then(r => r.text()).then(html => {
                            if (rowsTbody) rowsTbody.innerHTML = html;
                            // re-bind actions for newly-inserted elements
                            bindRowActions();
                        }).catch(() => {
                            // on error, show fallback message
                            if (rowsTbody) rowsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Could not load results.</td></tr>';
                        });
                    }

                    function debounceFetch() {
                        if (tick) clearTimeout(tick);
                        tick = setTimeout(fetchRows, 300);
                    }

                    // bind initial action handlers for server-rendered rows
                    bindRowActions();

                    if (searchInput) searchInput.addEventListener('input', debounceFetch);
                    if (rtomSelect) rtomSelect.addEventListener('change', debounceFetch);
                });
                </script>
                @endpush

            @endsection
