@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step active"></span>
    <span class="process-step"></span>
    <span class="process-step"></span>
</div>
@if(session('user.is_admin'))
<a href="#" class="btn btn-outline-secondary">Configurations</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        @if(session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
        @endif

        <form id="master-upload-form" action="{{ route('master.upload.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="card process-upload-card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h1 class="process-upload-title mb-1">Upload Master Dataset</h1>
                                <p class="text-muted mb-0">Upload a ZIP file containing a single Microsoft Excel (.xlsx) workbook with the required headers.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Back</a>
                            <button type="submit" class="btn btn-dark px-4">Submit</button>
                        </div>
                    </div>

                    <div id="master-upload-errors" class="mt-4 mb-0">
                        @if($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <p class="mb-2 fw-semibold">Please resolve the following issues before continuing:</p>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>

                    <div class="process-dropzone mt-4" id="master-dropzone">
                        <input type="file" class="visually-hidden" id="upload" name="upload" accept=".zip" required>
                        <label for="upload" class="process-dropzone-content text-center" tabindex="0" role="button">
                            <p class="process-dropzone-title mb-1">Drag and drop file or click to browse</p>
                            <p class="text-muted mb-0" id="master-dropzone-helper">Upload a .zip that contains your master Excel workbook.</p>
                        </label>
                    </div>

                    <div class="process-guidelines mt-4">
                        <h2 class="process-guidelines-title">File requirements</h2>
                        <ul class="mb-0">
                            <li>Compress the master Microsoft Excel workbook into a ZIP archive before uploading.</li>
                            <li>Each upload must contain exactly one Excel (.xlsx) workbook with the agreed master dataset headers.</li>
                            <li>Numeric columns such as <strong>LATEST_BILL_MNY</strong> and the arrears column must contain valid numbers or the character <strong>-</strong>.</li>
                            <li>Optional columns may be empty, but required columns must be present for every populated row.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>

        @if($process)
        <div class="card shadow-sm mt-4">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h5 mb-3">Latest upload summary</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Token</dt>
                    <dd class="col-sm-8">{{ $process->token }}</dd>

                    <dt class="col-sm-4">Dataset month</dt>
                    <dd class="col-sm-8">{{ $process->dataset_month }}</dd>

                    <dt class="col-sm-4">Rows imported</dt>
                    <dd class="col-sm-8">{{ number_format((int) $process->row_count) }}</dd>

                    <dt class="col-sm-4">Excluded rows</dt>
                    <dd class="col-sm-8">{{ number_format((int) $process->excluded_count) }}</dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">{{ ucfirst($process->status) }}</dd>
                </dl>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="{{ route('process.exclusions.create') }}" class="btn btn-outline-secondary" data-loader-off="1">Manage exclusions</a>
                    <a href="{{ route('process.assignments.index') }}" class="btn btn-dark" data-loader-off="1">Go to assignments</a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('master-upload-form');
    const dropzone = document.getElementById('master-dropzone');
    const fileInput = document.getElementById('upload');
    const helper = document.getElementById('master-dropzone-helper');
    const errorsContainer = document.getElementById('master-upload-errors');

    const updateHelper = (file) => {
        if (!helper) {
            return;
        }
        helper.textContent = file ? file.name : 'Upload a .zip that contains your master Excel workbook.';
    };

    const renderError = (message) => {
        if (!errorsContainer) {
            return;
        }

        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.setAttribute('role', 'alert');
        alert.innerHTML = `<p class="mb-2 fw-semibold">Please resolve the following issues before continuing:</p><ul class="mb-0"><li>${message}</li></ul>`;
        errorsContainer.innerHTML = '';
        errorsContainer.appendChild(alert);
        alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    if (dropzone) {
        dropzone.addEventListener('click', (event) => {
            if (event.target && event.target.closest && event.target.closest('label')) {
                return;
            }
            fileInput?.click();
        });

        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('is-dragover');
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
            if (!event.dataTransfer?.files?.length) {
                return;
            }

            const [file] = event.dataTransfer.files;
            if (file && file.name.toLowerCase().endsWith('.zip')) {
                fileInput.files = event.dataTransfer.files;
                updateHelper(file);
            } else {
                updateHelper(null);
                renderError('Please upload a ZIP file that contains your master Excel workbook.');
            }
        });
    }

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (file && file.name.toLowerCase().endsWith('.zip')) {
            updateHelper(file);
        } else {
            fileInput.value = '';
            updateHelper(null);
            if (file) {
                renderError('Please upload a ZIP file that contains your master Excel workbook.');
            }
        }
    });

    form?.addEventListener('submit', (event) => {
        if (!fileInput?.files?.length) {
            event.preventDefault();
            renderError('Please choose a ZIP file before submitting.');
        }
    });
});
</script>
@endpush
@endsection
