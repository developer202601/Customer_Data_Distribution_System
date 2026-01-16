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
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Regions & Region Admins</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.users.create') }}" class="btn btn-outline-success rounded-pill px-4">Add Region Admin</a>
                    </div>
                </div>

                {{-- status popup shown elsewhere; removed inline alert --}}

                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 mb-0">Region Admins</h2>
                            <form method="get" action="{{ route('cc.super.regions') }}" class="d-flex gap-2" id="cc-super-regions-filter">
                                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search username or name" value="{{ old('q', $q ?? request('q')) }}">
                                <select name="region" class="form-select form-select-sm">
                                    <option value="">All Regions</option>
                                    @foreach(($regions ?? collect()) as $r)
                                        <option value="{{ $r }}" {{ (string)($selectedRegion ?? request('region')) === (string)$r ? 'selected' : '' }}>{{ $r }}</option>
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
                                <tbody id="cc-region-rows">
                                    @include('cc.super._rows', ['regionAdmins' => $regionAdmins])
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
                    <form id="cc-disable-form" method="post" style="display:none">@csrf @method('put')<input type="hidden" name="return_to" value="{{ url()->current() }}"></form>
                    <form id="cc-enable-form" method="post" style="display:none">@csrf @method('put')<input type="hidden" name="return_to" value="{{ url()->current() }}"></form>
                    <form id="cc-delete-form" method="post" style="display:none">@csrf @method('delete')<input type="hidden" name="return_to" value="{{ url()->current() }}"></form>

                    @push('scripts')
                    <script>
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

                        const bindRowActions = () => {
                            document.querySelectorAll('.cc-disable-btn').forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    pendingAction = btn.getAttribute('data-action');
                                    const uname = btn.getAttribute('data-username') || '—';
                                    if (disableUsernameEl) disableUsernameEl.textContent = uname;
                                    if (disableConfirmBtn) disableConfirmBtn.textContent = 'Disable';
                                    disableModal?.show();
                                });
                            });

                            document.querySelectorAll('.cc-enable-btn').forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    pendingAction = btn.getAttribute('data-action');
                                    const uname = btn.getAttribute('data-username') || '—';
                                    if (disableUsernameEl) disableUsernameEl.textContent = uname;
                                    if (disableConfirmBtn) disableConfirmBtn.textContent = 'Enable';
                                    disableModal?.show();
                                });
                            });

                            document.querySelectorAll('.cc-delete-btn').forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    pendingAction = btn.getAttribute('data-action');
                                    const uname = btn.getAttribute('data-username') || '—';
                                    if (deleteUsernameEl) deleteUsernameEl.textContent = uname;
                                    deleteModal?.show();
                                });
                            });
                        };

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

                        const bindRegionRows = () => {
                            bindRowActions();
                        };

                        const rowsBody = document.getElementById('cc-region-rows');
                        const searchInput = document.querySelector('#cc-super-regions-filter input[name="q"]');
                        const regionSelect = document.querySelector('#cc-super-regions-filter select[name="region"]');
                        const filterForm = document.getElementById('cc-super-regions-filter');
                        let filterTick = null;

                        const renderLoadingRows = () => {
                            if (!rowsBody) return;
                            rowsBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>Loading…</td></tr>';
                        };

                        const fetchRegionRows = () => {
                            if (!rowsBody) return;
                            renderLoadingRows();
                            const params = new URLSearchParams();
                            const q = searchInput ? searchInput.value.trim() : '';
                            const region = regionSelect ? regionSelect.value : '';
                            if (q) params.set('q', q);
                            if (region) params.set('region', region);
                            fetch("{{ route('cc.super.regions.search') }}?" + params.toString(), {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            })
                                .then(r => r.text())
                                .then(html => {
                                    if (rowsBody) rowsBody.innerHTML = html;
                                    bindRegionRows();
                                })
                                .catch(() => {
                                    if (rowsBody) rowsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Unable to load results.</td></tr>';
                                });
                        };

                        const scheduleFetch = () => {
                            if (filterTick) clearTimeout(filterTick);
                            filterTick = setTimeout(fetchRegionRows, 250);
                        };

                        if (filterForm) {
                            filterForm.addEventListener('submit', function (event) {
                                event.preventDefault();
                                fetchRegionRows();
                            });
                        }
                        if (searchInput) {
                            searchInput.addEventListener('input', scheduleFetch);
                        }
                        if (regionSelect) {
                            regionSelect.addEventListener('change', fetchRegionRows);
                        }
                        bindRegionRows();
                        // preload data after binding
                        fetchRegionRows();
                    });
                    </script>
                    @endpush
@endsection