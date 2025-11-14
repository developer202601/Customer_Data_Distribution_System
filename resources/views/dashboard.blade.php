@extends('layouts.admin')

@section('navbar-right')
@if(session('user.is_admin'))
<a href="#" class="btn btn-outline-secondary mr-2">Configurations</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="content dashboard-content">
    <div class="container-fluid dashboard-container">
        <div class="dashboard-grid row g-4">
            <div class="col-lg-6">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-body">
                        <!-- <p class="dashboard-card-eyebrow">Operational Objective</p> -->
                        <h2 class="dashboard-card-title">View Past Reports</h2>
                        <p class="dashboard-card-description">Browse and download previously generated output files for quick reference.</p>
                        <div class="text-end">
                            <a href="#" class="btn btn-outline-dark">Open Archive</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-body">
                        <!-- <p class="dashboard-card-eyebrow">Operational Objective</p> -->
                        <h2 class="dashboard-card-title">Process New Excel File</h2>
                        <p class="dashboard-card-description">Upload the latest Excel inputs to validate data and run automated workflows.</p>
                        <div class="text-end">
                            <a href="{{ route('process.upload.create') }}" class="btn btn-outline-dark">Start Upload</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection