@extends('layouts.cc')

@section('title', 'Users')

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
                @php
                    $assignment = session('user.assignment') ?? '';
                    $isRtomAdmin = str_starts_with($assignment, 'rtom_');
                @endphp

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Regional Billing Administration</p>
                        <h1 class="process-upload-title mb-0">{{ $isRtomAdmin ? 'Caller Management' : 'User Management' }}</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#rbAddUserModal">
                            {{ $isRtomAdmin ? 'Add Caller' : 'Add User' }}
                        </button>
                    </div>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="table-responsive cc-table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Assignment</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @include('regionalbilling.users._rows', ['users' => $users])
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rbAddUserModal" tabindex="-1" aria-labelledby="rbAddUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rbAddUserLabel">{{ $isRtomAdmin ? 'Add Caller' : 'Add Regional Billing User' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rb-create-user-form" action="{{ route('rb.users.store') }}" method="post">
                    @csrf
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" maxlength="6" class="form-control" placeholder="6-digit ID" pattern="\d{6}" required>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Display name">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="rb-create-user-btn" class="btn btn-warning rounded-pill px-4">{{ $isRtomAdmin ? 'Create Caller' : 'Create User' }}</button>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const createBtn = document.getElementById('rb-create-user-btn');
    const createForm = document.getElementById('rb-create-user-form');
    if (createBtn && createForm) {
        createBtn.addEventListener('click', function () {
            createForm.submit();
        });
    }
});
</script>
@endsection
