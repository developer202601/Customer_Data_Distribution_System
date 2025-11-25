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
    @include('partials.process-toast', ['title' => 'Upload complete'])
    <div class="container-fluid">
        <form id="process-upload-form"
            action="{{ route('process.upload.store') }}"
            method="post"
            enctype="multipart/form-data"
            data-progress-url-template="{{ route('process.upload.progress', ['token' => '__TOKEN__']) }}"
            data-complete-url-template="{{ route('process.upload.complete', ['token' => '__TOKEN__']) }}"
            data-cancel-url="{{ route('process.upload.cancel') }}"
            data-loader-off="true">
            @csrf
            <div class="card process-upload-card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <!-- <span class="process-upload-icon d-inline-flex align-items-center justify-content-center">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <rect x="3" y="3" width="18" height="18" rx="2" stroke="#6f6f6f" stroke-width="1.5" />
                                    <path d="M8 11.5L10.5 14L14 9" stroke="#6f6f6f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle cx="8" cy="8" r="1" fill="#6f6f6f" />
                                </svg>
                            </span> -->
                            <div>
                                <h1 class="process-upload-title mb-1">Upload Here</h1>
                                <p class="text-muted mb-0">Upload a ZIP file that contains a single Microsoft Excel (.xlsx) workbook.</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Back</a>
                        <button type="submit" class="btn btn-dark px-4">Submit</button>
                    </div>

                    <div id="upload-errors" class="mt-4 mb-0">
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

                    <div class="process-dropzone mt-4" id="process-dropzone">
                        <input type="file" class="visually-hidden" id="upload" name="upload" accept=".zip">
                        <label for="upload" class="process-dropzone-content text-center" tabindex="0" role="button">
                            <p class="process-dropzone-title mb-1">Drag and drop file or click to browse</p>
                            <p class="text-muted mb-0" id="process-dropzone-helper">Upload a .zip that contains your Excel workbook.</p>
                        </label>
                    </div>
                    <!-- @error('upload')
                    <small class="text-danger d-block mt-2">{{ $message }}</small>
                    @enderror -->

                    <div class="process-guidelines mt-4">
                        <h2 class="process-guidelines-title">File requirements</h2>
                        <ul class="mb-0">
                            <li>Compress your Microsoft Excel workbook into a ZIP archive before uploading it here.</li>
                            <li>Each upload must contain exactly one Excel (.xlsx) workbook with the header row shown in the data dictionary.</li>
                            <li>The columns <strong>ADDRESS_NAME</strong>, <strong>EMAIL_ADDRESS</strong>, <strong>CREDIT_SCORE</strong>, <strong>SALES_PERSON</strong>, and <strong>SALES_CHANNEL</strong> may be empty.</li>
                            <li><strong>LATEST_BILL_MNY</strong> must contain a numeric amount or the character <strong>-</strong>.</li>
                            <li>All other columns must have values for every populated row.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('process-upload-form');
        const dropzone = document.getElementById('process-dropzone');
        const fileInput = document.getElementById('upload');
        const helper = document.getElementById('process-dropzone-helper');
        const errorsContainer = document.getElementById('upload-errors');
        const loader = document.getElementById('page-loader');
        const progressBar = loader?.querySelector('[data-loader-progress-bar]');
        const progressWrapper = loader?.querySelector('.page-loader__progress');
        const progressText = loader?.querySelector('[data-loader-progress-text]');
        const progressMessage = loader?.querySelector('[data-loader-progress-message]');
        const progressRows = loader?.querySelector('[data-loader-progress-rows]');
        const submitButton = form?.querySelector('button[type="submit"]');
        const cancelUrl = form?.dataset.cancelUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const POLL_INTERVAL_MS = 600;
        let pollTimer = null;
        let currentToken = null;
        let pollInFlight = false;
        let cancellationSent = false;
        let processingActive = false;

        if (!form || !dropzone || !fileInput) {
            return;
        }

        const updateHelper = (file) => {
            helper.textContent = file ? file.name : 'Upload a .zip that contains your Excel workbook.';
        };

        const clearErrors = () => {
            if (errorsContainer) {
                errorsContainer.innerHTML = '';
            }
        };

        const renderErrors = (messages) => {
            const issues = messages && messages.length ? messages : ['Something went wrong. Please try again.'];

            if (!errorsContainer) {
                alert(issues.join('\n'));
                return;
            }

            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.setAttribute('role', 'alert');

            const heading = document.createElement('p');
            heading.className = 'mb-2 fw-semibold';
            heading.textContent = 'Please resolve the following issues before continuing:';
            alert.appendChild(heading);

            const list = document.createElement('ul');
            list.className = 'mb-0';
            issues.forEach((message) => {
                const item = document.createElement('li');
                item.textContent = message;
                list.appendChild(item);
            });
            alert.appendChild(list);

            errorsContainer.innerHTML = '';
            errorsContainer.appendChild(alert);
            errorsContainer.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        };

        const setLoaderVisibility = (visible) => {
            if (!loader) {
                return;
            }

            if (visible) {
                loader.classList.remove('page-loader--hidden');
                loader.classList.add('page-loader--show-progress');
            } else {
                loader.classList.add('page-loader--hidden');
                loader.classList.remove('page-loader--show-progress');
            }
        };

        const formatPercentage = (value) => {
            if (!Number.isFinite(value)) {
                return '0';
            }

            const rounded = value % 1 === 0 ? value.toFixed(0) : value.toFixed(1);
            return rounded.replace(/\.0$/, '');
        };

        const updateLoader = (value, message, payload = null) => {
            if (!loader) {
                return;
            }

            setLoaderVisibility(true);

            const numericValue = Number(value);
            const safeValue = Number.isFinite(numericValue) ?
                Math.max(0, Math.min(100, numericValue)) :
                0;

            if (progressBar) {
                progressBar.style.width = safeValue + '%';
            }

            if (progressWrapper) {
                progressWrapper.setAttribute('aria-valuenow', String(safeValue));
            }

            if (progressText) {
                progressText.textContent = formatPercentage(safeValue) + '%';
            }

            if (progressMessage && message) {
                progressMessage.textContent = message;
            }

            if (progressRows) {
                const rowsLabel = buildRowsLabel(payload);
                progressRows.textContent = rowsLabel;
                progressRows.style.visibility = rowsLabel ? 'visible' : 'hidden';
            }
        };

        const hideLoader = () => {
            setLoaderVisibility(false);
            if (progressBar) {
                progressBar.style.width = '0%';
            }
            if (progressText) {
                progressText.textContent = '0%';
            }
            if (progressWrapper) {
                progressWrapper.setAttribute('aria-valuenow', '0');
            }
            if (progressMessage) {
                progressMessage.textContent = 'Preparing data…';
            }
            if (progressRows) {
                progressRows.textContent = '';
                progressRows.style.visibility = 'hidden';
            }
        };

        const extractMessages = (payload) => {
            if (!payload) {
                return ['An unexpected error occurred. Please try again.'];
            }

            if (Array.isArray(payload)) {
                return payload;
            }

            if (payload.errors) {
                return Object.values(payload.errors).flat();
            }

            if (payload.error) {
                return [payload.error];
            }

            if (payload.message) {
                return [payload.message];
            }

            return ['An unexpected error occurred. Please try again.'];
        };

        const stopPolling = (preserveToken = false) => {
            if (pollTimer) {
                window.clearInterval(pollTimer);
                pollTimer = null;
            }
            if (!preserveToken) {
                currentToken = null;
            }
            pollInFlight = false;
        };

        const sendCancellation = (reason = 'Processing cancelled.') => {
            if (!cancelUrl || !currentToken || cancellationSent) {
                return;
            }

            cancellationSent = true;
            const token = currentToken;

            const body = new URLSearchParams();
            body.append('_token', csrfToken);
            body.append('token', token);
            body.append('reason', reason);
            const payload = body.toString();

            if (navigator.sendBeacon) {
                const blob = new Blob([payload], {
                    type: 'application/x-www-form-urlencoded'
                });
                navigator.sendBeacon(cancelUrl, blob);
                return;
            }

            fetch(cancelUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: payload,
                keepalive: true,
            }).catch(() => {
                // swallow errors; cancellation best-effort
            });
        };

        const requestCancel = (reason = 'Processing cancelled.') => {
            if (!processingActive) {
                return;
            }

            processingActive = false;
            sendCancellation(reason);
            currentToken = null;
        };

        const buildProgressMessage = (payload) => {
            if (!payload) {
                return 'Processing…';
            }

            const base = payload.message || 'Processing…';
            const processed = Number(payload.processed_rows);
            const total = Number(payload.total_rows);

            if (Number.isFinite(processed) && Number.isFinite(total) && total > 0) {
                return `${base} (${processed.toLocaleString()} / ${total.toLocaleString()} rows)`;
            }

            if (Number.isFinite(processed) && processed > 0 && !Number.isFinite(total)) {
                return `${base} (${processed.toLocaleString()} rows processed)`;
            }

            return base;
        };

        const buildRowsLabel = (payload) => {
            if (!payload) {
                return '';
            }

            const processed = Number(payload.processed_rows);
            const total = Number(payload.total_rows);

            if (Number.isFinite(processed) && Number.isFinite(total) && total > 0) {
                return `${processed.toLocaleString()} / ${total.toLocaleString()} rows`;
            }

            if (Number.isFinite(processed) && processed > 0) {
                return `${processed.toLocaleString()} rows processed`;
            }

            return '';
        };

        const deriveProgressValue = (payload) => {
            if (!payload) {
                return null;
            }

            const processed = Number(payload.processed_rows);
            const total = Number(payload.total_rows);

            if (!Number.isFinite(processed) || !Number.isFinite(total) || total <= 0) {
                return null;
            }

            const ratio = Math.max(0, processed) / total;
            const computed = 5 + (ratio * 90);

            if (!Number.isFinite(computed)) {
                return null;
            }

            return Math.max(0, Math.min(100, computed));
        };

        const pollProgress = (token) => {
            const progressTemplate = form.dataset.progressUrlTemplate;
            const completeTemplate = form.dataset.completeUrlTemplate;

            if (!progressTemplate || !completeTemplate) {
                hideLoader();
                renderErrors(['Progress routes are unavailable. Please refresh the page and try again.']);
                if (submitButton) {
                    submitButton.disabled = false;
                }
                return;
            }

            const progressUrl = progressTemplate.replace('__TOKEN__', token);
            const completeUrl = completeTemplate.replace('__TOKEN__', token);

            const performPoll = async () => {
                if (pollInFlight) {
                    return;
                }

                pollInFlight = true;

                try {
                    const response = await fetch(progressUrl, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    const payload = await response.json().catch(() => null);

                    if (!response.ok || !payload) {
                        throw payload ?? {
                            message: 'Unable to read progress updates.'
                        };
                    }

                    const derivedProgress = deriveProgressValue(payload);
                    const progressValue = Number.isFinite(derivedProgress) ?
                        derivedProgress :
                        Number(payload.progress ?? 0);

                    updateLoader(progressValue, buildProgressMessage(payload), payload);

                    if (payload.status === 'failed') {
                        requestCancel(payload.error || payload.message || 'Processing failed.');
                        throw {
                            message: payload.error || payload.message || 'Processing failed.'
                        };
                    }

                    if (payload.status === 'complete') {
                        processingActive = false;
                        cancellationSent = true;
                        stopPolling();
                        updateLoader(100, 'Finalising results…', payload);
                        window.setTimeout(() => {
                            window.location.href = completeUrl;
                        }, 400);
                    }
                } catch (error) {
                    stopPolling(true);
                    requestCancel('Progress updates failed.');
                    hideLoader();
                    renderErrors(extractMessages(error));
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                } finally {
                    pollInFlight = false;
                }
            };

            performPoll();
            pollTimer = window.setInterval(performPoll, POLL_INTERVAL_MS);
        };

        const handleUpload = async () => {
            clearErrors();
            updateLoader(5, 'Uploading file…');

            if (submitButton) {
                submitButton.disabled = true;
            }

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload?.token) {
                    throw payload ?? {
                        message: 'Unable to start processing. Please try again.'
                    };
                }

                currentToken = payload.token;
                cancellationSent = false;
                processingActive = true;
                pollProgress(payload.token);
            } catch (error) {
                hideLoader();
                renderErrors(extractMessages(error));
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        };

        dropzone.addEventListener('click', (event) => {
            // If the click came from the label (or its children) the browser
            // will already activate the associated input. Avoid calling
            // `fileInput.click()` in that case to prevent the file picker
            // from opening twice.
            if (event.target && event.target.closest && event.target.closest('label')) {
                return;
            }

            fileInput.click();
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
                alert('Please upload a ZIP file that contains your Excel workbook.');
            }
        });

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];

            if (file && file.name.toLowerCase().endsWith('.zip')) {
                updateHelper(file);
            } else {
                fileInput.value = '';
                updateHelper(null);
                if (file) {
                    alert('Please upload a ZIP file that contains your Excel workbook.');
                }
            }
        });

        form.addEventListener('submit', (event) => {
            if (!window.fetch) {
                return;
            }

            event.preventDefault();

            if (!fileInput.files.length) {
                renderErrors(['Please choose a ZIP file containing your Excel workbook before submitting.']);
                return;
            }

            if (pollTimer) {
                return;
            }

            handleUpload();
        });

        window.addEventListener('beforeunload', () => {
            if (processingActive && currentToken) {
                sendCancellation('Browser closed before processing finished.');
            }
        });
    });
</script>
@endsection