@extends('layouts.cc')

@section('title', 'User Management')

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
                        <h1 class="process-upload-title mb-0">User Management</h1>
                        <p class="text-muted small mb-0">Manage users and assign roles across the hierarchy.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.users.index') }}" class="btn btn-outline-primary rounded-pill px-4">Legacy Users</a>
                    </div>
                </div>

                @if(session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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

                <div class="row g-4">
                    <div class="col-12">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h6 mb-0">Users</h2>
                                <div class="d-flex gap-3 align-items-center">
                                    <form id="cc-mgmt-filter-form" class="d-flex" method="get" action="{{ route('cc.management.index') }}">
                                        <select name="status" class="form-select form-select-sm me-2">
                                            <option value="all" {{ ($filter_status ?? 'all') === 'all' ? 'selected' : '' }}>All Status</option>
                                            <option value="active" {{ ($filter_status ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="disabled" {{ ($filter_status ?? '') === 'disabled' ? 'selected' : '' }}>Disabled</option>
                                        </select>
                                        <select name="role" class="form-select form-select-sm me-2">
                                            <option value="all" {{ ($filter_role ?? 'all') === 'all' ? 'selected' : '' }}>All Roles</option>
                                            @if(isset($allowedRoles['super']))
                                            <option value="super_admin" {{ ($filter_role ?? '') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                                            @endif
                                            @if(isset($allowedRoles['region']))
                                            <option value="region_admin" {{ ($filter_role ?? '') === 'region_admin' ? 'selected' : '' }}>Region Admin</option>
                                            @endif
                                            @if(isset($allowedRoles['rtom_admin']))
                                            <option value="rtom_admin" {{ ($filter_role ?? '') === 'rtom_admin' ? 'selected' : '' }}>RTOM Admin</option>
                                            @endif
                                            @if(isset($allowedRoles['supervisor']))
                                            <option value="supervisor" {{ ($filter_role ?? '') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                                            @endif
                                            @if(isset($allowedRoles['caller']))
                                            <option value="caller" {{ ($filter_role ?? '') === 'caller' ? 'selected' : '' }}>Caller</option>
                                            @endif
                                        </select>
                                        <input name="q" type="search" class="form-control form-control-sm me-2" placeholder="Search username or name" value="{{ $filter_q ?? '' }}" style="width:200px">
                                    </form>
                                    <span class="text-muted small">{{ $users->count() }} users</span>
                                </div>
                            </div>
                            <div class="table-responsive cc-table-container">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Supervisor</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($users as $user)
                                        @php
                                            $assignment = $user->assignment ?? '';
                                            if ($assignment === 'super') {
                                                $roleLabel = 'Super Admin';
                                            } elseif (str_starts_with($assignment, 'REGION')) {
                                                $region = str_replace('REGION ', '', $assignment);
                                                $roleLabel = 'Region Admin for ' . $region;
                                            } elseif (str_starts_with($assignment, 'rtom_')) {
                                                $rtom = str_replace('rtom_', '', $assignment);
                                                $roleLabel = 'RTOM Admin for ' . $rtom;
                                            } elseif (str_starts_with($assignment, 'supervisor_rtom_')) {
                                                $rtom = str_replace('supervisor_rtom_', '', $assignment);
                                                $roleLabel = 'Supervisor for RTO ' . $rtom;
                                            } elseif (str_starts_with($assignment, 'caller_rtom_')) {
                                                $rtom = str_replace('caller_rtom_', '', $assignment);
                                                $roleLabel = 'Caller for RTO ' . $rtom;
                                            } else {
                                                $roleLabel = $user->admin_prev ? 'Call Center Admin' : 'Call Center User';
                                            }
                                            $currentUserId = session('user')['id'] ?? null;
                                            if ($user->supervisor && $user->supervisor == $currentUserId) {
                                                $supervisorLabel = 'Me';
                                            } else {
                                                $supervisorLabel = optional($user->supervisorUser)->name
                                                    ? (optional($user->supervisorUser)->name . ' (' . optional($user->supervisorUser)->username . ')')
                                                    : '—';
                                            }
                                            $canDelete = ! $user->fixed
                                                && (int)($user->supervised_users_count ?? 0) === 0
                                                && (int)($user->interactions_as_agent_count ?? 0) === 0
                                                && (int)($user->row_assignments_count ?? 0) === 0;
                                        @endphp
                                        @php
                                            // Compute badge class
                                            if ($assignment === 'super') {
                                                $badgeClass = 'danger';
                                            } elseif (str_starts_with($assignment, 'REGION')) {
                                                $badgeClass = 'warning';
                                            } elseif (str_starts_with($assignment, 'rtom_')) {
                                                $badgeClass = 'info';
                                            } elseif (str_starts_with($assignment, 'supervisor_')) {
                                                $badgeClass = 'primary';
                                            } elseif (str_starts_with($assignment, 'caller_')) {
                                                $badgeClass = 'secondary';
                                            } else {
                                                $badgeClass = 'light';
                                            }
                                        @endphp
                                        <tr>
                                            <td>{{ $user->username }}</td>
                                            <td>{{ $user->name ?: '—' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ $roleLabel }}
                                                </span>
                                            </td>
                                            <td>{{ $supervisorLabel }}</td>
                                            <td>
                                                @if($user->status)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Disabled</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <a href="{{ route('cc.management.assign', $user) }}" class="btn btn-sm btn-outline-warning rounded-pill" title="Change Role">Assign Role</a>
                                                    @if($user->status)
                                                    <button type="button" class="btn btn-sm btn-warning rounded-pill mgmt-disable-btn"
                                                        data-action="{{ route('cc.management.toggle-status', $user) }}"
                                                        data-username="{{ $user->username }}"
                                                        data-action-label="disable">Disable</button>
                                                    @else
                                                    <button type="button" class="btn btn-sm btn-success rounded-pill mgmt-enable-btn"
                                                        data-action="{{ route('cc.management.toggle-status', $user) }}"
                                                        data-username="{{ $user->username }}"
                                                        data-action-label="enable">Enable</button>
                                                    @endif
                                                    @if($canDelete)
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill mgmt-delete-btn"
                                                        data-action="{{ route('cc.management.destroy', $user) }}"
                                                        data-username="{{ $user->username }}">Delete</button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No users found matching your filters.</td>
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
</div>

<!-- Disable/Enable confirmation modal -->
<div class="modal fade" id="mgmtToggleConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to <strong id="mgmtToggleActionLabel">disable</strong> the following user?</p>
                <p class="mb-0"><strong>Username:</strong> <span id="mgmtToggleUsername">—</span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="mgmtToggleConfirm" class="btn btn-warning rounded-pill px-4">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="mgmtDeleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This action will permanently delete the user. This cannot be undone. Continue?</p>
                <p class="mb-0"><strong>Username:</strong> <span id="mgmtDeleteUsername">—</span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="mgmtDeleteConfirm" class="btn btn-danger rounded-pill px-4">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for POST actions -->
<form id="mgmt-toggle-form" method="post" style="display:none">@csrf @method('put')</form>
<form id="mgmt-delete-form" method="post" style="display:none">@csrf @method('delete')</form>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function() {
    // Toggle (enable/disable) handler
    let toggleActionUrl = '';
    document.querySelectorAll('.mgmt-disable-btn, .mgmt-enable-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.dataset.action;
            const username = this.dataset.username;
            const actionLabel = this.dataset.actionLabel || 'disable';
            toggleActionUrl = action;
            document.getElementById('mgmtToggleActionLabel').textContent = actionLabel;
            document.getElementById('mgmtToggleUsername').textContent = username;
            const modal = new bootstrap.Modal(document.getElementById('mgmtToggleConfirmModal'));
            modal.show();
        });
    });
    document.getElementById('mgmtToggleConfirm').addEventListener('click', function() {
        if (toggleActionUrl) {
            const form = document.getElementById('mgmt-toggle-form');
            form.action = toggleActionUrl;
            form.submit();
        }
    });

    // Delete handler
    let deleteActionUrl = '';
    document.querySelectorAll('.mgmt-delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.dataset.action;
            const username = this.dataset.username;
            deleteActionUrl = action;
            document.getElementById('mgmtDeleteUsername').textContent = username;
            const modal = new bootstrap.Modal(document.getElementById('mgmtDeleteConfirmModal'));
            modal.show();
        });
    });
    document.getElementById('mgmtDeleteConfirm').addEventListener('click', function() {
        if (deleteActionUrl) {
            const form = document.getElementById('mgmt-delete-form');
            form.action = deleteActionUrl;
            form.submit();
        }
    });

    // Auto-submit filters on change
    document.querySelectorAll('#cc-mgmt-filter-form select').forEach(el => {
        el.addEventListener('change', function() { this.form.submit(); });
    });
})();
</script>
@endpush
