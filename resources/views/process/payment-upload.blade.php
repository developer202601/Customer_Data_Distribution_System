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
        <form id="payment-upload-form" action="{{ route('payments.update') }}" method="post" enctype="multipart/form-data">
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

@if(session('payment_status'))
<div class="alert alert-success mt-4" role="alert">
    {{ session('payment_status.message') }}
</div>
@endif

@if($errors->has('upload'))
<div class="alert alert-danger mt-4" role="alert">
    {{ $errors->first('upload') }}
</div>
@endif
                </div>
            </div>
        </form>

    </div>
</div>

@push('scripts')
<script>
(function() {
    const form = document.getElementById('payment-upload-form');
    const fileInput = document.getElementById('upload');
    const submitBtn = document.getElementById('payment-upload-submit');
    const progressBlock = document.getElementById('payment-upload-progress-block');
    const progressLabel = document.getElementById('payment-upload-progress-label');
    const progressFile = document.getElementById('payment-upload-progress-file');
    const progressMeta = document.getElementById('payment-upload-progress-meta');
    const progressBar = document.getElementById('payment-upload-progress-bar');
    const clearBtn = document.getElementById('payment-upload-clear');
    const dropzone = document.getElementById('payment-dropzone');
    const dropzoneHelper = document.getElementById('payment-dropzone-helper');

    if (!fileInput || !submitBtn) {
        return;
    }

    function showProgress(file) {
        if (!progressBlock || !progressLabel || !progressFile || !progressMeta || !progressBar || !clearBtn) {
            return;
        }

        progressBlock.classList.remove('d-none');
        progressLabel.textContent = 'Processing payment file';
        progressFile.textContent = file ? file.name : '';
        progressMeta.textContent = '';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        submitBtn.disabled = true;
        clearBtn.classList.add('d-none');
    }

    function finishProgress(message, isError) {
        if (!progressBlock || !progressLabel || !progressBar || !clearBtn) {
            return;
        }

        progressLabel.textContent = message || (isError ? 'Upload failed' : 'Upload complete');
        progressBar.style.width = isError ? '0%' : '100%';
        progressBar.textContent = isError ? '0%' : '100%';
        progressBar.classList.remove('progress-bar-animated');
        if (isError) {
            progressBar.classList.remove('bg-success');
            progressBar.classList.add('bg-danger');
        }
        submitBtn.disabled = false;
        clearBtn.classList.remove('d-none');
    }

    function resetForm() {
        if (form) {
            form.reset();
        }
        if (submitBtn) {
            submitBtn.classList.add('d-none');
            submitBtn.disabled = true;
        }
        if (progressBlock) {
            progressBlock.classList.add('d-none');
        }
        if (dropzoneHelper) {
            dropzoneHelper.textContent = 'Upload your Payment Excel workbook (.xlsx).';
        }
    }

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            submitBtn.classList.remove('d-none');
            if (dropzoneHelper) {
                dropzoneHelper.textContent = this.files[0].name;
            }
        }
    });

    if (dropzone) {
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('border-primary');
        });

        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('border-primary');
        });

        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('border-primary');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function() {
            const file = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;
            if (!file) {
                return;
            }
            showProgress(file);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            resetForm();
        });
    }
})();
</script>
@endpush
@endsection