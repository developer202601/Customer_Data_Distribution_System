@extends('layouts.admin')

@section('navbar-right')
@if(session('user.is_admin'))
<a href="{{ route('admin.config') }}" class="btn btn-outline-secondary mr-2">Configurations</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="content dashboard-content">

    <div class="dashboard-section-header mb-2 mt-4">
        <h2 class="h4 mb-0">Choose an option below</h2>
        <p class="text-muted mb-0">Start a new processing run or review previously generated outputs.</p>
    </div>

    <div class="dashboard-grid row g-4">
        <div class="col-lg-6">
            <a href="#" class="dashboard-card h-100" role="button" aria-label="Open Archive">
                <div class="dashboard-card-body">
                    <h2 class="dashboard-card-title">View Past Reports</h2>
                    <p class="dashboard-card-description">Browse and download previously generated output files for quick reference.</p>
                </div>
            </a>
        </div>
        <div class="col-lg-6">
            <a href="{{ route('process.upload.create') }}" class="dashboard-card h-100" role="button" aria-label="Start Upload">
                <div class="dashboard-card-body">
                    <h2 class="dashboard-card-title">Process New Excel File</h2>
                    <p class="dashboard-card-description">Upload the latest Excel inputs to validate data and run automated workflows.</p>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection