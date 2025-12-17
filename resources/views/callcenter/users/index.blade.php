@extends('layouts.cc')

@section('navbar-right')
<a href="{{ route('cc.dashboard') }}" class="btn btn-outline-secondary">Call Center Home</a>
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
                        <h1 class="process-upload-title mb-0">User Management</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#ccAddUserModal">Add User</button>
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

        <div class="row g-4">
            <div class="col-12">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0">Existing Users</h2>
                        <span class="text-muted small">{{ $users->count() }} users</span>
                    </div>
                        <div class="table-responsive cc-table-container">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Fixed</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $user)
                                    <tr>
                                        <td>{{ $user->username }}</td>
                                        <td>{{ $user->admin_prev ? 'Call Center Admin' : 'Call Center User' }}</td>
                                        <td>
                                            @if($user->status)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Disabled</span>
                                            @endif
                                        </td>
                                        <td>{{ $user->fixed ? 'Yes' : 'No' }}</td>
                                        <td>{{ optional($user->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <a href="{{ route('cc.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Edit</a>
                                                @if($user->status)
                                                <button type="button" class="btn btn-sm btn-warning rounded-pill cc-disable-btn" data-action="{{ route('cc.users.disable', $user) }}" data-username="{{ $user->username }}">Disable</button>
                                                @endif
                                                @if(!$user->fixed)
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill cc-delete-btn" data-action="{{ route('cc.users.destroy', $user) }}" data-username="{{ $user->username }}">Delete</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No call center users yet.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Add User modal -->
        <div class="modal fade" id="ccAddUserModal" tabindex="-1" aria-labelledby="ccAddUserLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ccAddUserLabel">Add Call Center User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="cc-create-user-form" action="{{ route('cc.users.store') }}" method="post">
                            @csrf
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" id="username" value="{{ old('username') }}" maxlength="6" class="form-control @error('username') is-invalid @enderror" placeholder="6-digit ID" pattern="\\d{6}" required>
                                @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-check mb-3">
                                <input type="hidden" name="admin_prev" value="0">
                                <input type="checkbox" name="admin_prev" id="admin_prev" value="1" class="form-check-input" {{ old('admin_prev') ? 'checked' : '' }}>
                                <label for="admin_prev" class="form-check-label">Call Center Admin</label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="cc-create-user-btn" class="btn btn-warning rounded-pill px-4">Create User</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Confirmation modal for creating admin users -->
        <div class="modal fade" id="ccAdminConfirmModal" tabindex="-1" aria-labelledby="ccAdminConfirmLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ccAdminConfirmLabel">Confirm Admin Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are creating a Call Center administrator account. Administrators can access sensitive configuration and user-management pages.</p>
                        <p class="mb-2"><strong>Username:</strong> <span id="ccConfirmUsername">—</span></p>
                        <div class="mb-3">
                            <label for="ccConfirmUsernameInput" class="form-label">Type the username to confirm</label>
                            <input id="ccConfirmUsernameInput" type="text" class="form-control" placeholder="Type username to confirm">
                            <div id="ccConfirmValidationMessage" class="form-text text-danger mt-1" style="display:none"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="ccConfirmCreate" class="btn btn-success rounded-pill px-4" disabled>Confirm & Create</button>
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
                        <p>Are you sure you want to disable the following user?</p>
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
        <form id="cc-delete-form" method="post" style="display:none">@csrf @method('delete')</form>

    </div>
</div>
@endsection

    @push('styles')
    <style>
    /* Page-specific: keep footer flush but preserve CC layout padding; only remove bottom spacing */
    .cc-layout .content-wrapper { padding-bottom: 0 !important; }
    </style>
    @endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('cc-create-user-form');
    const adminCheckbox = document.getElementById('admin_prev');
    const usernameInput = document.getElementById('username');
    const confirmModalEl = document.getElementById('ccAdminConfirmModal');
    const confirmUsername = document.getElementById('ccConfirmUsername');
    const confirmCreateBtn = document.getElementById('ccConfirmCreate');

    if (!form) return;

    // Bootstrap modal instance (if bootstrap loaded)
    let bsModal = null;
    if (window.bootstrap && confirmModalEl) {
        bsModal = new bootstrap.Modal(confirmModalEl, { keyboard: true });
    }

    // When using the modal trigger button, handle clicks from the modal footer button
    const modalCreateBtn = document.getElementById('cc-create-user-btn');
    if (modalCreateBtn) {
        modalCreateBtn.addEventListener('click', function (e) {
            // If admin checked, show confirm modal first
            if (adminCheckbox && adminCheckbox.checked) {
                e.preventDefault();
                if (confirmUsername) confirmUsername.textContent = usernameInput?.value || '—';
                if (bsModal) {
                    bsModal.show();
                } else {
                    if (confirm('You are creating a Call Center administrator account. Continue?')) {
                        form.submit();
                    }
                }
                return;
            }

            // otherwise submit the form directly
            form.submit();
        });
    }

    if (confirmCreateBtn) {
        const confirmInput = document.getElementById('ccConfirmUsernameInput');
        const confirmMessage = document.getElementById('ccConfirmValidationMessage');
        confirmCreateBtn.addEventListener('click', function () {
            if (bsModal) bsModal.hide();
            form.submit();
        });

        // enable/disable confirm button based on input match
        if (confirmInput) {
            confirmInput.addEventListener('input', function () {
                const expected = (confirmUsername && confirmUsername.textContent) ? confirmUsername.textContent.trim() : '';
                const val = confirmInput.value.trim();
                if (!val) {
                    confirmMessage.style.display = 'block';
                    confirmMessage.textContent = 'Please type the username to confirm.';
                    confirmCreateBtn.disabled = true;
                    return;
                }
                if (val !== expected) {
                    confirmMessage.style.display = 'block';
                    confirmMessage.textContent = 'Typed username does not match.';
                    confirmCreateBtn.disabled = true;
                    return;
                }
                confirmMessage.style.display = 'none';
                confirmMessage.textContent = '';
                confirmCreateBtn.disabled = false;
            });
        }
    }

    // Disable/Delete buttons: use modals and hidden forms
    const disableBtns = document.querySelectorAll('.cc-disable-btn');
    const deleteBtns = document.querySelectorAll('.cc-delete-btn');
    const disableModalEl = document.getElementById('ccDisableConfirmModal');
    const deleteModalEl = document.getElementById('ccDeleteConfirmModal');
    const disableUsernameEl = document.getElementById('ccDisableConfirmUsername');
    const deleteUsernameEl = document.getElementById('ccDeleteConfirmUsername');
    const disableConfirmBtn = document.getElementById('ccDisableConfirm');
    const deleteConfirmBtn = document.getElementById('ccDeleteConfirm');
    const disableForm = document.getElementById('cc-disable-form');
    const deleteForm = document.getElementById('cc-delete-form');

    let pendingAction = null;
    let disableModal = null;
    let deleteModal = null;
    if (window.bootstrap && disableModalEl) disableModal = new bootstrap.Modal(disableModalEl);
    if (window.bootstrap && deleteModalEl) deleteModal = new bootstrap.Modal(deleteModalEl);

    disableBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            pendingAction = btn.getAttribute('data-action');
            const uname = btn.getAttribute('data-username') || '—';
            if (disableUsernameEl) disableUsernameEl.textContent = uname;
            if (disableModal) disableModal.show();
        });
    });

    deleteBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            pendingAction = btn.getAttribute('data-action');
            const uname = btn.getAttribute('data-username') || '—';
            if (deleteUsernameEl) deleteUsernameEl.textContent = uname;
            if (deleteModal) deleteModal.show();
        });
    });

    if (disableConfirmBtn) {
        disableConfirmBtn.addEventListener('click', () => {
            if (!pendingAction) return;
            disableForm.action = pendingAction;
            disableModal?.hide();
            disableForm.submit();
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
