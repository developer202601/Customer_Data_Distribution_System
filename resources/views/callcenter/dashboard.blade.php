@extends('layouts.cc')

@section('navbar-right')
<a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Master Portal</a>
@if(session('user.is_admin'))
<a href="{{ route('cc.users.index') }}" class="btn btn-primary">Manage Call Center Users</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="content">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                <div>
                    <p class="text-uppercase text-muted mb-1">Call Center</p>
                    <h1 class="h4 mb-2">Call Center Portal</h1>
                    <p class="mb-0 text-muted">Manage call center operations and user access from this dedicated area.</p>
                </div>
                @if(session('user.is_admin'))
                <a href="{{ route('cc.users.index') }}" class="btn btn-primary">Go to User Management</a>
                @endif
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <h2 class="h6">Access Policies</h2>
                        <p class="mb-0 text-muted">Only call center accounts can access these pages. Administrators can create, edit, disable, or delete call center users.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <h2 class="h6">Next Steps</h2>
                        <ul class="mb-0 text-muted ps-3">
                            <li>Review current call center users</li>
                            <li>Create new call center accounts with admin privileges if required</li>
                            <li>Disable accounts instead of deleting when flagged as fixed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
