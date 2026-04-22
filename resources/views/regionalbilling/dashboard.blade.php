@extends('layouts.cc')

@section('title', 'RBC Overview')

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
                        <p class="text-uppercase text-muted mb-1">Regional Billing Centre</p>
                        <h1 class="process-upload-title mb-0">Overview</h1>
                        <p class="text-muted">Use this landing page to manage regions, users, and the RBC report workflow.</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Regions</h5>
                                <p class="card-text">Browse and manage RBC region admins and containers.</p>
                                <a href="{{ route('rb.regions.index') }}" class="btn btn-primary">Regions</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Users</h5>
                                <p class="card-text">Create and manage RBC users across regions.</p>
                                <a href="{{ route('rb.users.index') }}" class="btn btn-primary">Manage Users</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Reports</h5>
                                <p class="card-text">Check current regional billing report progress and review history.</p>
                                <a href="{{ route('rb.reports') }}" class="btn btn-primary">Reports</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection