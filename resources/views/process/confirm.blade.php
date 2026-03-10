@extends('layouts.admin')

@section('title', 'Assignment Confirmation')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step skipped"></span>
    <span class="process-step skipped"></span>
    <span class="process-step active"></span>
    <span class="process-step"></span>
</div>
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
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h1 class="process-upload-title mb-1">Configuration Confirmation</h1>
                        <p class="text-muted mb-0">Review the dataset analysis and adjust assignment configurations before proceeding.</p>
                    </div>
                </div>

                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <div>
                        <h4 class="alert-heading h5">Dataset Analysis</h4>
                        <p class="mb-0">
                            Found <strong>{{ number_format($ftthCount) }}</strong> Retail and Micro Business FTTH (Fiber) records in the uploaded master dataset (after applying exclusions).
                        </p>
                    </div>
                </div>

                <form action="{{ route('process.confirm.store') }}" method="post">
                    @csrf
                    
                    <h5 class="mb-3">Assignment Configuration</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="upper_range" class="form-label">Upper Range (LKR)</label>
                            <input type="number" class="form-control" id="upper_range" name="upper_range" value="{{ old('upper_range', $assignmentConfig['upper_range']) }}" required min="0">
                            <div class="form-text">Upper limit for bill value selection.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="lower_range" class="form-label">Lower Range (LKR)</label>
                            <input type="number" class="form-control" id="lower_range" name="lower_range" value="{{ old('lower_range', $assignmentConfig['lower_range']) }}" required min="0">
                            <div class="form-text">Lower limit for bill value selection.</div>
                        </div>
                    </div>

                    <h5 class="mb-3">Quotas</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="call_center_staff_quota" class="form-label">Call Center Staff Quota</label>
                            <input type="number" class="form-control" id="call_center_staff_quota" name="call_center_staff_quota" value="{{ old('call_center_staff_quota', $assignmentConfig['call_center_staff_quota']) }}" required min="0">
                        </div>
                        <div class="col-md-4">
                            <label for="call_center_quota" class="form-label">Call Center Quota</label>
                            <input type="number" class="form-control" id="call_center_quota" name="call_center_quota" value="{{ old('call_center_quota', $assignmentConfig['call_center_quota']) }}" required min="0">
                        </div>
                        <div class="col-md-4">
                            <label for="staff_quota" class="form-label">Staff Quota</label>
                            <input type="number" class="form-control" id="staff_quota" name="staff_quota" value="{{ old('staff_quota', $assignmentConfig['staff_quota']) }}" required min="0">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary px-4">Confirm & Process</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
