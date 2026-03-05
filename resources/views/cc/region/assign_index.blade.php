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
                        <h1 class="process-upload-title mb-0">Assign Supervisors</h1>
                        <div class="mt-2 text-muted">Your RTO: <strong>{{ session('user.assignment') }}</strong></div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.region.create_supervisor') }}" class="btn btn-outline-success rounded-pill px-4">Add Supervisors</a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="table-responsive cc-table-container">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $u)
                                        <tr>
                                                <td>{{ $u->username }}</td>
                                                <td>{{ $u->name ?? '—' }}</td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('cc.region.edit_supervisor', $u) }}">Edit</a>
                                                    @if(($u->status ?? 1) == 1)
                                                        <button type="button" class="btn btn-sm btn-warning rounded-pill cc-disable-btn" data-action="{{ route('cc.region.disable_supervisor', $u) }}" data-username="{{ $u->username }}">Disable</button>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-success rounded-pill cc-enable-btn" data-action="{{ route('cc.region.enable_supervisor', $u) }}" data-username="{{ $u->username }}">Enable</button>
                                                    @endif
                                                    @if(empty($u->fixed))
                                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill cc-delete-btn" data-action="{{ route('cc.region.destroy_supervisor', $u) }}" data-username="{{ $u->username }}">Delete</button>
                                                    @endif
                                                </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No users to show</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

                <!-- Delete/Disable modals and hidden forms (reused from region index) -->
                <div class="modal fade" id="ccDisableConfirmModal" tabindex="-1" aria-labelledby="ccDisableConfirmLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="ccDisableConfirmLabel">Confirm</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p id="ccDisableConfirmBody">Are you sure?</p>
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
                });
                </script>
                @endpush