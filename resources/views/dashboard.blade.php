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
        <!-- <div class="dashboard-hero shadow-sm">
            <div class="dashboard-hero__text">
                <span class="dashboard-eyebrow">Executive Summary</span>
                <h1>Customer Data Distribution System</h1>
                <p>SLT currently relies on manual Excel-based processes to segment customer data. This dashboard aligns with the new login experience—calm, bright, and focused on clarity.</p>
                <div class="dashboard-hero__cta">
                    <a href="{{ route('process.upload.create') }}" class="btn btn-primary">Process a New Run</a>
                    <a href="#" class="btn btn-outline-secondary">Review Past Outputs</a>
                </div>
            </div>
            <div class="dashboard-hero__notes">
                <div>
                    <h3>Business Need</h3>
                    <p>Automate uploads, filtering, and distribution of customer lists with reliable, auditable outcomes.</p>
                </div>
                <div>
                    <h3>High-Level Solution</h3>
                    <p>Internal tool that ingests Excel files, applies business rules, classifies VIPs, and exports per segment.</p>
                </div>
                <div>
                    <h3>Benefits</h3>
                    <ul>
                        <li>Faster processing & consistent segmentation</li>
                        <li>Reduced manual workload & rework</li>
                        <li>Traceable, auditable runs</li>
                    </ul>
                </div>
            </div>
        </div> -->

        <div class="dashboard-section-header mb-3">
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
</div>
@endsection