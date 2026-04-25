@extends('layouts.cc')

@section('title', 'Edit User')

@section('navbar-right')
<a href="{{ route('rb.users.index') }}" class="btn btn-outline-secondary">Back to Users</a>
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <div class="mb-3">
                    <p class="text-uppercase text-muted mb-1">Regional Billing</p>
                    <h1 class="process-upload-title mb-0">Edit {{ $user->username }}</h1>
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

                <form action="{{ route('rb.users.update', $user) }}" method="post" class="row g-3">
                    @csrf
                    @method('put')
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="{{ $user->username }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Assignment</label>
                        <input type="text" class="form-control" value="{{ $user->assignment ?: '—' }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-success rounded-pill px-4">Save Changes</button>
                        <a href="{{ route('rb.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
