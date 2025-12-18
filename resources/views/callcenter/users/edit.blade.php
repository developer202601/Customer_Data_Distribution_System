@extends('layouts.cc')

@section('navbar-right')
<a href="{{ route('cc.users.index') }}" class="btn btn-outline-secondary">Back to Users</a>
<a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Master Portal</a>
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <p class="text-uppercase text-muted mb-1">Edit User</p>
                <h1 class="process-upload-title mb-0">{{ $user->username }}</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('cc.users.index') }}" class="btn btn-outline-secondary px-4">Back to Users</a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Master Portal</a>
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

        <div class="card process-upload-card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <form action="{{ route('cc.users.update', $user) }}" method="post" class="row g-3">
                    @csrf
                    @method('put')
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        @if($user->fixed)
                            <input type="text" class="form-control" value="{{ $user->username }}" readonly>
                        @else
                            @if(!$user->status)
                                {{-- User is disabled: allow username edit --}}
                                <input name="username" type="text" class="form-control" value="{{ old('username', $user->username) }}" maxlength="6" pattern="\d{6}" required>
                                <div class="form-text">Username may only be changed for disabled users.</div>
                            @else
                                {{-- Active users cannot change username --}}
                                <input type="text" class="form-control" value="{{ $user->username }}" readonly>
                                <div class="form-text text-muted">Username cannot be changed while the user is active. Disable the user to change it.</div>
                            @endif
                        @endif
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="admin_prev" value="0">
                            <input class="form-check-input" type="checkbox" value="1" id="admin_prev" name="admin_prev" {{ $user->admin_prev ? 'checked' : '' }}>
                            <label class="form-check-label" for="admin_prev">Call Center Admin</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="1" {{ $user->status ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ !$user->status ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fixed</label>
                        <input type="text" class="form-control" value="{{ $user->fixed ? 'Yes' : 'No' }}" readonly>
                        <small class="text-muted">Fixed users cannot be deleted.</small>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-success rounded-pill px-4">Save Changes</button>
                        <a href="{{ route('cc.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
