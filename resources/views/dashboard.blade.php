@extends('layouts.admin')

@section('title', 'Dashboard')

@section('navbar-right')
{{--
@if(session('user.is_admin'))
<a href="{{ route('admin.config') }}" class="btn btn-outline-secondary mr-2">Configurations</a>
@endif
--}}
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="content dashboard-content">
    @if(session('status'))
    <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle me-2 fs-5"></i>
            <div>{{ session('status') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="dashboard-section-header mb-2 mt-4">
        <h2 class="h4 mb-0">Choose an option below</h2>
    </div>

    <div class="dashboard-grid row g-4">
        @if(session('user.is_admin') || session('user.system') === 'cc')
        <div class="col-lg-6">
            <a href="{{ route('process.assignments.reports') }}" class="dashboard-card h-100" role="button" aria-label="Open past reports">
                <div class="dashboard-card-body">
                    <h2 class="dashboard-card-title">View Reports</h2>
                    <p class="dashboard-card-description">Here, you can find monthly reports and download them.</p>
                </div>
            </a>
        </div>
        <div class="col-lg-6">
            <a href="{{ route('admin.config') }}" class="dashboard-card h-100" role="button" aria-label="Open configurations">
                <div class="dashboard-card-body">
                    <h2 class="dashboard-card-title">Configurations</h2>
                    <p class="dashboard-card-description">Manage configurations and user accounts settings.</p>
                </div>
            </a>
        </div>
        @else
        <div class="col-lg-6">
            <a href="{{ route('master.upload.create') }}" class="dashboard-card h-100" role="button" aria-label="Start master dataset upload">
                <div class="dashboard-card-body">
                    <h2 class="dashboard-card-title">Upload Master Dataset</h2>
                    <p class="dashboard-card-description">Upload the monthly master dataset</p>
                </div>
            </a>
        </div>
        <div class="col-lg-6">
            <a href="{{ route('payment.upload') }}" class="dashboard-card h-100" role="button" aria-label="Open payment files upload page">
                <div class="dashboard-card-body">
                    <h2 class="dashboard-card-title">Payment Files Upload</h2>
                    <p class="dashboard-card-description">Upload the monthly payment files</p>
                </div>
            </a>
        </div>
        @endif
    </div>
</div>
@endsection