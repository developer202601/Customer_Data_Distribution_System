@extends('layouts.admin')

@section('title', 'Upload Master Data')

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
        @if(!empty($process) && !empty($showProcessBanner))
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <div>
                <strong>Master file already uploaded.</strong>
                <div class="small mb-0">Process #{{ $process->id }} is currently in status: {{ ucfirst(str_replace('_', ' ', (string) $process->status)) }}.</div>
            </div>
            <a href="{{ route('process.exclusions.create') }}" class="btn btn-outline-primary btn-sm" data-loader-off="1">Continue to exclusions</a>
        </div>
        @endif

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
                                <p class="text-muted mb-0">Upload a Microsoft Excel (.xlsx) workbook with the required headers.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Back</a>
                            <button type="submit" class="btn btn-dark px-4 d-none" id="master-upload-submit" disabled>Submit</button>
                        </div>
                    </div>

                    <div id="master-upload-errors" class="mt-4 mb-0">
                        @if(!empty($processFailurePayload) && is_array($processFailurePayload))
                        <div class="alert alert-danger" role="alert">
                            <p class="mb-2 fw-semibold">Previous processing failed. Please fix and re-upload.</p>

                            @if(!empty($processFailurePayload['master_errors']))
                            <p class="mb-1 fw-semibold">Master file errors ({{ $processFailurePayload['master_file'] ?? 'master archive' }})</p>
                            <ul class="mb-2">
                                @foreach($processFailurePayload['master_errors'] as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            @endif

                            @if(!empty($processFailurePayload['exclusion_errors']))
                            <p class="mb-1 fw-semibold">Exclusion file errors</p>
                            <ul class="mb-2">
                                @foreach($processFailurePayload['exclusion_errors'] as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            @endif

                            @if(!empty($processFailurePayload['general_errors']))
                            <p class="mb-1 fw-semibold">Other errors</p>
                            <ul class="mb-0">
                                @foreach($processFailurePayload['general_errors'] as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            @endif
                        </div>
                        @endif

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

                    <div class="mt-4 d-none border rounded-4 p-3 bg-white" id="master-upload-progress-block">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <strong id="master-upload-progress-label">Waiting to upload</strong>
                                <p class="text-muted small mb-0" id="master-upload-progress-file"></p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small" id="master-upload-progress-meta"></span>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm d-none"
                                    id="master-upload-clear"
                                    aria-label="Remove uploaded file"
                                    title="Remove uploaded file"
                                >Remove uploaded file</button>
                            </div>
                        </div>
                        <div class="progress" id="master-upload-progress-track" style="height: 0.9rem;">
                            <div
                                id="master-upload-progress-bar"
                                class="progress-bar progress-bar-striped progress-bar-animated"
                                role="progressbar"
                                style="width: 0%; --bs-progress-bar-bg: var(--btn-success-bg);"
                            >0%</div>
                        </div>
                    </div>

                    <div class="process-dropzone mt-4" id="master-dropzone">
                        <input type="file" class="visually-hidden" id="upload" name="upload" accept=".xlsx" required>
                        <label for="upload" class="process-dropzone-content text-center" tabindex="0" role="button">
                            <p class="process-dropzone-title mb-1">Drag and drop file or click to browse</p>
                            <p class="text-muted mb-0" id="master-dropzone-helper">Upload your master Excel workbook (.xlsx).</p>
                        </label>
                    </div>

                    <div class="process-guidelines mt-4">
                        <h2 class="process-guidelines-title">File requirements</h2>
                        <ul class="mb-0">
                            <li>Upload the master Microsoft Excel workbook directly (.xlsx).</li>
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
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('master-upload-form');
    const dropzone = document.getElementById('master-dropzone');
    const fileInput = document.getElementById('upload');
    const submitButton = document.getElementById('master-upload-submit');
    const clearButton = document.getElementById('master-upload-clear');
    const helper = document.getElementById('master-dropzone-helper');
    const errorsContainer = document.getElementById('master-upload-errors');
    const progressBlock = document.getElementById('master-upload-progress-block');
    const progressTrack = document.getElementById('master-upload-progress-track');
    const progressBar = document.getElementById('master-upload-progress-bar');
    const progressLabel = document.getElementById('master-upload-progress-label');
    const progressFile = document.getElementById('master-upload-progress-file');
    const progressMeta = document.getElementById('master-upload-progress-meta');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const startRoute = @json(route('master.upload.chunks.start'));
    const partRoute = @json(route('master.upload.chunks.part'));
    const finishRoute = @json(route('master.upload.chunks.finish'));
    const progressStreamTemplate = @json(route('process.upload.progress.stream', ['token' => '__TOKEN__']));
    const progressPollTemplate = @json(route('process.upload.progress', ['token' => '__TOKEN__']));
    const submitRoute = @json(route('master.upload.chunks.submit'));
    const cancelRouteTemplate = @json(route('master.upload.chunks.cancel', ['token' => '__TOKEN__']));
    const stagedDestroyRouteTemplate = @json(route('master.upload.chunks.staged.destroy', ['token' => '__TOKEN__']));
    let isUploading = false;
    let isUploaded = false;
    let activeUploadToken = null;
    let stagedUploadToken = null;
    let activeAbortController = null;

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0 B';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        let index = 0;
        let value = bytes;
        while (value >= 1024 && index < units.length - 1) {
            value /= 1024;
            index += 1;
        }
        return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    };

    const formatSpeed = (bytesPerSecond) => {
        if (!Number.isFinite(bytesPerSecond) || bytesPerSecond <= 0) {
            return '0 B/s';
        }

        return `${formatBytes(bytesPerSecond)}/s`;
    };

    const setProgress = (percentage, label, meta, options = {}) => {
        if (!progressBlock || !progressBar) {
            return;
        }

        const hideBar = options?.hideBar === true;

        progressBlock.classList.remove('d-none');
        progressTrack?.classList.toggle('d-none', hideBar);

        // Ensure percentage reaches 100% when complete
        if (options?.stage === 'completed') {
            percentage = 100;
        }
        
        progressBar.style.width = `${percentage}%`;
        progressBar.textContent = `${percentage}%`;
        progressBar.setAttribute('aria-valuenow', String(percentage));
        if (progressLabel) {
            progressLabel.textContent = label;
        }
        if (progressMeta) {
            progressMeta.textContent = meta;
        }
    };

    const hideProgress = () => {
        progressBlock?.classList.add('d-none');
        progressTrack?.classList.remove('d-none');
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressBar.classList.remove('bg-danger');
        }
        updateProgressFile(null);
    };

    const updateHelper = (file) => {
        if (!helper) {
            return;
        }
        helper.textContent = file ? `${file.name} (${formatBytes(file.size)})` : 'Upload your master Excel workbook (.xlsx).';
    };

    const updateProgressFile = (file) => {
        if (!progressFile) {
            return;
        }

        progressFile.textContent = file ? `${file.name} (${formatBytes(file.size)})` : '';
    };

    const renderError = (fileName, messages) => {
        if (!errorsContainer) {
            return;
        }

        // Backward-compatible signature: renderError('message')
        if (messages === undefined) {
            messages = fileName;
            fileName = null;
        }

        const filteredMessages = (Array.isArray(messages) ? messages : [messages])
            .filter((entry) => typeof entry === 'string' && entry.trim() !== '');

        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.setAttribute('role', 'alert');

        const heading = document.createElement('p');
        heading.className = 'mb-2 fw-semibold';
        heading.textContent = fileName ? `Please resolve the following issues for "${fileName}" before continuing:` : 'Please resolve the following issues before continuing:';

        const list = document.createElement('ul');
        list.className = 'mb-0';

        if (filteredMessages.length === 0) {
            const fallback = document.createElement('li');
            fallback.textContent = 'Unable to continue.';
            list.appendChild(fallback);
        } else {
            filteredMessages.forEach((entry) => {
                const item = document.createElement('li');
                item.textContent = entry;
                list.appendChild(item);
            });
        }

        alert.appendChild(heading);
        alert.appendChild(list);
        errorsContainer.innerHTML = '';
        errorsContainer.appendChild(alert);
        // Avoid auto-scrolling while user may be reading/clicking around
        // alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const updateSubmitState = () => {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = isUploading || !isUploaded || !stagedUploadToken;
        submitButton.classList.toggle('d-none', !(isUploaded && stagedUploadToken));
    };

    const clearError = () => {
        if (errorsContainer) {
            errorsContainer.innerHTML = '';
        }
    };

    const updateClearButtonLabel = () => {
        if (!clearButton) {
            return;
        }

        clearButton.classList.toggle('d-none', !(isUploading || isUploaded || Boolean(fileInput?.files?.length)));
        const label = isUploading ? 'Cancel upload' : 'Remove uploaded file';
        clearButton.textContent = label;
        clearButton.setAttribute('title', label);
        clearButton.setAttribute('aria-label', label);
    };

    const updateDropzoneState = () => {
        if (!dropzone) {
            return;
        }

        dropzone.classList.toggle('d-none', isUploading || isUploaded);
    };

    const resetState = (preserveInput = false) => {
        if (fileInput) {
            fileInput.value = preserveInput ? fileInput.value : '';
        }
        isUploading = false;
        isUploaded = false;
        activeUploadToken = null;
        stagedUploadToken = null;
        activeAbortController = null;
        if (submitButton) {
            submitButton.textContent = 'Submit';
            submitButton.disabled = true;
        }
        if (clearButton) {
            clearButton.disabled = false;
        }
        updateHelper(null);
        hideProgress();
        updateClearButtonLabel();
        updateDropzoneState();
        updateSubmitState();
    };

    const deleteStagedUpload = async () => {
        if (!stagedUploadToken) {
            return;
        }

        const route = stagedDestroyRouteTemplate.replace('__TOKEN__', encodeURIComponent(stagedUploadToken));
        try {
            await fetch(route, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
            });
        } catch (_error) {
            // Ignore cleanup errors; stale files can be handled by retention jobs.
        }
        stagedUploadToken = null;
        isUploaded = false;
        updateSubmitState();
    };

    const cancelActiveUpload = async () => {
        if (!activeUploadToken) {
            return;
        }

        const route = cancelRouteTemplate.replace('__TOKEN__', encodeURIComponent(activeUploadToken));
        try {
            await fetch(route, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
            });
        } catch (_error) {
            // Ignore cleanup errors; user can re-upload immediately.
        }
        activeUploadToken = null;
    };

    const startProgressStream = (token) => {
        const streamUrl = progressStreamTemplate.replace('__TOKEN__', encodeURIComponent(token));
        const pollUrl = progressPollTemplate.replace('__TOKEN__', encodeURIComponent(token));

        if (typeof window.EventSource === 'undefined') {
            return startProgressPoll(pollUrl);
        }

        let source = null;
        try {
            source = new EventSource(streamUrl, { withCredentials: true });
        } catch (_error) {
            return startProgressPoll(pollUrl);
        }

        const handlePayload = (data) => {
            if (!data) {
                return;
            }

            const message = data.message || 'Processing…';
            const status = data.status;
            const progressValue = typeof data.progress === 'number' ? Math.round(data.progress) : 100;
            const heartbeat = buildHeartbeat(data.last_updated_at);

            setProgress(
                Math.min(100, Math.max(0, progressValue)),
                message,
                status === 'awaiting_exclusions'
                    ? `Upload complete. Please submit and add exclusions to begin processing.${heartbeat ? ' • ' + heartbeat : ''}`
                    : heartbeat
            );

            if (['ready', 'failed', 'canceled', 'awaiting_exclusions'].includes(status)) {
                source?.close();
                source = null;
            }
        };

        source.onmessage = (event) => {
            if (!event?.data) {
                return;
            }
            try {
                handlePayload(JSON.parse(event.data));
            } catch (_error) {}
        };

        source.onerror = () => {
            source?.close();
            source = null;
            startProgressPoll(pollUrl);
        };

        return source;
    };

    const startProgressPoll = (url) => {
        let pollTimer = null;
        const poll = async () => {
            try {
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
                if (!resp.ok) return;
                const data = await resp.json();
                const message = data.message || 'Processing…';
                const progressValue = typeof data.progress === 'number' ? Math.round(data.progress) : 100;
                const heartbeat = buildHeartbeat(data.last_updated_at);
                setProgress(
                    Math.min(100, Math.max(0, progressValue)),
                    message,
                    data.status === 'awaiting_exclusions'
                        ? `Upload complete. Please submit and add exclusions to begin processing.${heartbeat ? ' • ' + heartbeat : ''}`
                        : heartbeat
                );

                if (['ready', 'failed', 'canceled', 'awaiting_exclusions'].includes(data.status)) {
                    clearInterval(pollTimer);
                }
            } catch (_error) {}
        };

        const buildHeartbeat = (timestamp) => {
            if (!timestamp) {
                return '';
            }

            const last = new Date(timestamp).getTime();
            if (Number.isNaN(last)) {
                return '';
            }

            const delta = Math.max(0, Math.floor((Date.now() - last) / 1000));
            if (delta > 5) {
                return `Stalled ${delta}s`;
            }

            return `Active ${delta}s`;
        };

        poll();
        pollTimer = setInterval(poll, 2000);
        return pollTimer;
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
            if (file && file.name.toLowerCase().endsWith('.xlsx')) {
                fileInput.files = event.dataTransfer.files;
                updateHelper(file);
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                updateHelper(null);
                renderError('Please upload the master Excel workbook (.xlsx).');
            }
        });
    }

    const uploadSelectedFile = async (file) => {
        const requestHeaders = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        };

        isUploading = true;
        isUploaded = false;
        updateClearButtonLabel();
        updateDropzoneState();
        updateSubmitState();
        updateProgressFile(file);
        setProgress(0, 'Preparing upload', formatBytes(file.size));
        clearError();

        try {
            await deleteStagedUpload();

            const startPayload = new FormData();
            startPayload.append('file_name', file.name);
            startPayload.append('file_size', String(file.size));
            startPayload.append('mime_type', file.type || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            activeAbortController = new AbortController();

            const startResponse = await fetch(startRoute, {
                method: 'POST',
                body: startPayload,
                headers: requestHeaders,
                credentials: 'same-origin',
                signal: activeAbortController.signal,
            });
            const startJson = await startResponse.json().catch(() => null);
            if (!startResponse.ok || !startJson?.upload_token) {
                throw new Error(startJson?.message || startJson?.errors?.upload?.[0] || 'Unable to start the upload.');
            }

            activeUploadToken = startJson.upload_token;
            const chunkSize = Number(startJson.chunk_size || (2 * 1024 * 1024));
            const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
            let averageBytesPerSecond = 0;
            let previousUploadedBytes = 0;
            let previousTickAt = performance.now();

            for (let index = 0; index < totalChunks; index += 1) {
                const start = index * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);
                const chunkPayload = new FormData();
                chunkPayload.append('upload_token', activeUploadToken);
                chunkPayload.append('chunk_index', String(index));
                chunkPayload.append('chunk', chunk, `${file.name}.part${index}`);

                const chunkResponse = await fetch(partRoute, {
                    method: 'POST',
                    body: chunkPayload,
                    headers: requestHeaders,
                    credentials: 'same-origin',
                    signal: activeAbortController.signal,
                });
                const chunkJson = await chunkResponse.json().catch(() => null);
                if (!chunkResponse.ok) {
                    throw new Error(chunkJson?.message || 'Unable to upload a file chunk.');
                }

                const uploadedBytes = Math.min((index + 1) * chunkSize, file.size);
                const tickAt = performance.now();
                const elapsedSeconds = Math.max((tickAt - previousTickAt) / 1000, 0.001);
                const deltaBytes = Math.max(uploadedBytes - previousUploadedBytes, 0);
                const instantBytesPerSecond = deltaBytes / elapsedSeconds;
                averageBytesPerSecond = averageBytesPerSecond > 0
                    ? ((averageBytesPerSecond * 0.7) + (instantBytesPerSecond * 0.3))
                    : instantBytesPerSecond;
                previousUploadedBytes = uploadedBytes;
                previousTickAt = tickAt;
                const percentage = Math.min(100, Math.round((uploadedBytes / file.size) * 100)); // Upload: 0-100%
                setProgress(
                    percentage,
                    'Uploading master dataset',
                    `${formatBytes(uploadedBytes)} / ${formatBytes(file.size)} at ${formatSpeed(averageBytesPerSecond)}`
                );
            }

            const finishPayload = new FormData();
            finishPayload.append('upload_token', activeUploadToken);
            finishPayload.append('total_chunks', String(totalChunks));

            const finishResponse = await fetch(finishRoute, {
                method: 'POST',
                body: finishPayload,
                headers: requestHeaders,
                credentials: 'same-origin',
                signal: activeAbortController.signal,
            });
            const finishJson = await finishResponse.json().catch(() => null);
            if (!finishResponse.ok || !finishJson?.staged_upload_token) {
                throw new Error(finishJson?.message || finishJson?.errors?.upload?.[0] || 'Unable to finalize the uploaded file.');
            }

            activeUploadToken = null;
            stagedUploadToken = finishJson.staged_upload_token;
            window.masterUploadToken = finishJson.staged_upload_token;
            isUploading = false;
            isUploaded = true;
            activeAbortController = null;
            updateClearButtonLabel();
            startProgressStream(stagedUploadToken);
            setProgress(100, 'Upload complete', 'Click Submit to continue.', { stage: 'completed' });
            updateSubmitState();

        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                setProgress(0, 'Upload canceled', 'You can select a file again.');
                progressBar?.classList.add('bg-danger');
            } else {
                renderError(error instanceof Error ? error.message : 'Unable to upload the selected file.');
                setProgress(0, 'Upload failed', 'Please try again.');
                progressBar?.classList.add('bg-danger');
            }

            await cancelActiveUpload();
            isUploading = false;
            isUploaded = false;
            activeAbortController = null;
            stagedUploadToken = null;
            updateClearButtonLabel();
            updateDropzoneState();
            updateSubmitState();
        }
    };

    fileInput?.addEventListener('change', async () => {
        const file = fileInput.files[0];

        if (!file) {
            resetState();
            return;
        }

        if (!file.name.toLowerCase().endsWith('.xlsx')) {
            fileInput.value = '';
            updateHelper(null);
            renderError('Please upload the master Excel workbook (.xlsx).');
            updateSubmitState();
            return;
        }

        if (isUploading) {
            return;
        }

        updateHelper(file);
        await uploadSelectedFile(file);
    });

    clearButton?.addEventListener('click', async (event) => {
        event.preventDefault();

        if (isUploading) {
            activeAbortController?.abort();
            await cancelActiveUpload();
            resetState();
            return;
        }

        await deleteStagedUpload();
        resetState();
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isUploading) {
            return;
        }

        if (!stagedUploadToken) {
            renderError('Please wait until upload finishes before submitting.');
            return;
        }

        clearError();
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
        clearButton.disabled = true;

        try {
            const payload = new FormData();
            payload.append('staged_upload_token', stagedUploadToken);

            const response = await fetch(submitRoute, {
                method: 'POST',
                body: payload,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
            });

            const json = await response.json().catch(() => null);
            if (!response.ok) {
                const validationMessages = json?.errors ? Object.values(json.errors).flat() : [];
                throw validationMessages.length
                    ? validationMessages
                    : [json?.message || 'Unable to submit uploaded file.'];
            }

            window.location.href = json?.redirect_url || @json(route('process.exclusions.create'));
        } catch (error) {
            if (Array.isArray(error)) {
                renderError(error);
            } else {
                renderError(error instanceof Error ? error.message : 'Unable to submit uploaded file.');
            }
            submitButton.disabled = false;
            submitButton.textContent = 'Submit';
            clearButton.disabled = false;
        }
    });

    window.addEventListener('pageshow', (event) => {
        const navType = window.performance && window.performance.navigation
            ? window.performance.navigation.type
            : null;
        const restoredFromHistory = event.persisted || navType === 2;

        if (!restoredFromHistory) {
            return;
        }

        clearError();
        resetState();
    });

    resetState();
    updateSubmitState();
});
</script>
<script nonce="{{ $cspNonce ?? '' }}">
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
