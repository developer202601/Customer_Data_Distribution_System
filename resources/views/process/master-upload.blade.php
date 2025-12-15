@extends('layouts.admin')

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
                            <button type="button" class="btn btn-outline-secondary px-4" id="master-upload-clear">Remove file</button>
                            <button type="submit" class="btn btn-dark px-4" id="master-upload-submit" disabled>Submit</button>
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

                    @if(!empty($assignmentConfig))
                    <div class="process-config mt-4" id="assignment-config-block">
                        <h2 class="process-guidelines-title">Current allocation values</h2>
                        <!-- Outer grey panel for current allocation values -->
                        <div class="p-3" style="background:var(--surface-muted); border-radius:1rem;">
                            <div class="row g-3" id="assignment-config-cards">
                                <div class="col-md-6">
                                    <div class="border p-3 h-100 bg-light" style="border-radius:1rem;">
                                        <p class="text-muted mb-1">Retail / Micro arrears window</p>
                                        <p class="h5 mb-0">
                                            <span id="assignment-lower-range-value">{{ number_format($assignmentConfig['lower_range']) }}</span> –
                                            <span id="assignment-upper-range-value">{{ number_format($assignmentConfig['upper_range']) }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border p-3 h-100 bg-light" style="border-radius:1rem;">
                                        <p class="text-muted mb-1">Call centre quotas</p>
                                        <p class="mb-0">
                                            <strong>Call center staff:</strong>
                                            <span id="assignment-call-center-staff">{{ number_format($assignmentConfig['call_center_staff_quota']) }}</span><br>
                                            <strong>Call center:</strong>
                                            <span id="assignment-call-center">{{ number_format($assignmentConfig['call_center_quota']) }}</span><br>
                                            <strong>Staff:</strong>
                                            <span id="assignment-staff">{{ number_format($assignmentConfig['staff_quota']) }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="assignment-config-refresh">
                                Refresh numbers
                            </button>
                            <span class="text-muted small mb-0">Click refresh to load the latest updated numbers.</span>
                        </div>
                        <p class="text-muted small mt-1 mb-0" id="assignment-config-status" aria-live="polite"></p>
                    </div>
                    @endif
                </div>
            </div>
        </form>

    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('master-upload-form');
    const dropzone = document.getElementById('master-dropzone');
    const fileInput = document.getElementById('upload');
    const submitButton = document.getElementById('master-upload-submit');
    const clearButton = document.getElementById('master-upload-clear');
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

    const updateSubmitState = () => {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = !(fileInput?.files?.length);
    };

    const clearSelection = () => {
        if (fileInput) {
            fileInput.value = '';
        }
        updateHelper(null);
        if (errorsContainer) {
            errorsContainer.innerHTML = '';
        }
        updateSubmitState();
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
            updateSubmitState();
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

        updateSubmitState();
    });

    clearButton?.addEventListener('click', (event) => {
        event.preventDefault();
        clearSelection();
    });

    form?.addEventListener('submit', (event) => {
        if (!fileInput?.files?.length) {
            event.preventDefault();
            renderError('Please choose a ZIP file before submitting.');
        }
    });

    clearSelection();
    updateSubmitState();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const refreshButton = document.getElementById('assignment-config-refresh');
    const status = document.getElementById('assignment-config-status');
    const route = @json(route('master.upload.assignment.config'));
    const lower = document.getElementById('assignment-lower-range-value');
    const upper = document.getElementById('assignment-upper-range-value');
    const callCenterStaff = document.getElementById('assignment-call-center-staff');
    const callCenter = document.getElementById('assignment-call-center');
    const staff = document.getElementById('assignment-staff');

    const formatNumber = (value) => {
        if (value === null || value === undefined || value === '') {
            return value;
        }
        return new Intl.NumberFormat('en-US').format(Number(value));
    };

    const updateNumbers = (config) => {
        if (!config) {
            return;
        }

        if (lower) {
            lower.textContent = formatNumber(config.lower_range);
        }
        if (upper) {
            upper.textContent = formatNumber(config.upper_range);
        }
        if (callCenterStaff) {
            callCenterStaff.textContent = formatNumber(config.call_center_staff_quota);
        }
        if (callCenter) {
            callCenter.textContent = formatNumber(config.call_center_quota);
        }
        if (staff) {
            staff.textContent = formatNumber(config.staff_quota);
        }
    };

    if (!refreshButton) {
        return;
    }

    refreshButton.addEventListener('click', async () => {
        refreshButton.disabled = true;
        if (status) {
            status.textContent = 'Refreshing values…';
        }

        try {
            const response = await fetch(route, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to reach the server.');
            }

            const payload = await response.json();
            const config = payload?.assignmentConfig;

            if (!config) {
                throw new Error('Server returned an unexpected payload.');
            }

            updateNumbers(config);
            if (status) {
                status.textContent = 'Values refreshed at ' + new Date().toLocaleTimeString();
            }
        } catch (error) {
            if (status) {
                status.textContent = `Refresh failed: ${error instanceof Error ? error.message : 'unknown error'}`;
            }
        } finally {
            refreshButton.disabled = false;
        }
    });
});
</script>
@endpush
@endsection
