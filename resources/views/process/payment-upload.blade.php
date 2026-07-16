@extends('layouts.admin')

@section('title', 'Payment Files Upload')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step active"></span>
    <span class="process-step"></span>
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
        <form id="payment-upload-form" action="{{ route('master.upload.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="card process-upload-card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h1 class="process-upload-title mb-1">Payment Files Upload</h1>
                                <p class="text-muted mb-0">Upload a Microsoft Excel (.xlsx) workbook with the required headers.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Back</a>
                            <button type="submit" class="btn btn-dark px-4 d-none" id="payment-upload-submit" disabled>Submit</button>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-none border rounded-4 p-3 bg-white" id="payment-upload-progress-block">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <strong id="payment-upload-progress-label">Waiting to upload</strong>
                                <p class="text-muted small mb-0" id="payment-upload-progress-file"></p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small" id="payment-upload-progress-meta"></span>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm d-none"
                                    id="payment-upload-clear"
                                    aria-label="Remove uploaded file"
                                    title="Remove uploaded file">Remove uploaded file</button>
                            </div>
                        </div>
                        <div class="progress" id="payment-upload-progress-track" style="height: 0.9rem;">
                            <div
                                id="payment-upload-progress-bar"
                                class="progress-bar progress-bar-striped progress-bar-animated"
                                role="progressbar"
                                style="width: 0%; --bs-progress-bar-bg: var(--btn-success-bg);">0%</div>
                        </div>
                    </div>

                    <div class="process-dropzone mt-4" id="payment-dropzone">
                        <input type="file" class="visually-hidden" id="upload" name="upload" accept=".xlsx" required>
                        <label for="upload" class="process-dropzone-content text-center" tabindex="0" role="button">
                            <p class="process-dropzone-title mb-1">Drag and drop file or click to browse</p>
                            <p class="text-muted mb-0" id="payment-dropzone-helper">Upload your Payment Excel workbook (.xlsx).</p>
                        </label>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>
@endsection