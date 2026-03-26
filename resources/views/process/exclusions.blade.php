@extends('layouts.admin')

@section('title', 'Exclusions')

@section('loaderPollStatus', false)

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
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
        <!-- <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <strong>Upload Exclusion Files</strong>
                <p class="mb-0">Attach up to {{ $maxFiles }} Excel workbooks that describe the rows you want to exclude from the master dataset.</p>
            </div>
            <a href="{{ route('process.upload.preview') }}" class="btn btn-outline-secondary" data-loader-off="true">Back to preview</a>
        </div> -->

        <form id="exclusion-upload-form"
            action="{{ route('process.exclusions.store') }}"
            method="post"
            enctype="multipart/form-data">
            @csrf
            <div class="card process-upload-card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h1 class="process-upload-title mb-1">Upload exclusion sheets</h1>
                                <p class="text-muted mb-0">Upload up to {{ $maxFiles }} Excel (.xlsx) workbooks that list the identifiers you want to remove from the master list.</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                <a href="{{ route('master.upload.create') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
                                <button type="button" class="btn btn-outline-secondary" id="exclusion-clear">Clear all exclusions</button>
                                <button type="submit" class="btn btn-dark px-4" disabled>Apply exclusions</button>
                            </div>
                            <p class="text-muted mb-0 mt-2">You can add files one at a time or all at once.</p>
                        </div>
                    </div>

                    <div id="exclusion-errors" class="mt-4">
                        @if(session('status') && ! session('hide_dataset_info'))
                        <div class="alert alert-info" role="alert">
                            {{ session('status') }}
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

                    <div class="process-dropzone mt-4" id="exclusion-dropzone">
                        <input type="file" class="visually-hidden" id="exclusion-files" name="exclusions[]" accept=".xlsx" multiple>
                        <label for="exclusion-files" class="process-dropzone-content text-center" tabindex="0" role="button">
                            <p class="process-dropzone-title mb-1">Drag and drop or click to add Excel files</p>
                            <p class="text-muted mb-0" id="exclusion-dropzone-helper">
                                You can queue up to {{ $maxFiles }} .xlsx files. Each file must include the standard header row.
                            </p>
                        </label>
                    </div>

                    <div class="mt-4">
                        <h2 class="process-guidelines-title h5">Selected files</h2>
                        <div class="mb-3 d-none border rounded-4 p-3 bg-white" id="exclusion-upload-progress-block">
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                                <div>
                                    <strong id="exclusion-upload-progress-label">Waiting to upload</strong>
                                    <p class="text-muted small mb-0" id="exclusion-upload-progress-details"></p>
                                </div>
                                <span class="text-muted small" id="exclusion-upload-progress-meta"></span>
                            </div>
                            <div class="progress" id="exclusion-upload-progress-track" style="height: 0.9rem;">
                                <div
                                    id="exclusion-upload-progress-bar"
                                    class="progress-bar progress-bar-striped progress-bar-animated"
                                    role="progressbar"
                                    style="width: 0%; --bs-progress-bar-bg: var(--btn-success-bg);"
                                >0%</div>
                            </div>
                        </div>
                        <div id="exclusion-file-list" class="process-selected-files">
                            <p class="mb-0">No files selected yet.</p>
                        </div>
                    </div>

                    <div class="process-guidelines mt-4">
                        <h2 class="process-guidelines-title">Exclusion guidelines</h2>
                        <p class="mb-2">For the remaining non-VIP records, perform the exclusion process as follows:</p>
                        <ol class="mb-2">
                            <li><strong>Import the three exclusion workbooks:</strong> Add each exclusion file (up to three) to the form. Each file should be an Excel (.xlsx) workbook with the standard header row (for example, <code>CUSTOMER_REF</code> and <code>ACCOUNT_NUM</code>).</li>
                            <li><strong>Remove matches from the master list:</strong> After uploading, you can either let the system apply exclusions when you press <em>Apply exclusions</em>, or you may perform the matching yourself offline before uploading by using Excel functions such as <code>VLOOKUP</code>, <code>XLOOKUP</code> or conditional filters. Matching is performed when either <code>CUSTOMER_REF</code> or <code>ACCOUNT_NUM</code> appears in any exclusion workbook.</li>
                        </ol>
                        <ul class="mb-0">
                            <li>Each file must contain exactly one workbook with the standard header row (for example, <strong>CUSTOMER_REF</strong> and <strong>ACCOUNT_NUM</strong>).</li>
                            <li>You may add the three Excel files sequentially; they will appear in the list before you submit.</li>
                            <li>When you press <strong>Apply exclusions</strong>, the system will merge the rows and remove any matching rows from the master list.</li>
                            <li>Only Microsoft Excel (.xlsx) files are supported for this workflow.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>

        @if(isset($process) && ! empty($process->exclusion_archives))
        <div class="card shadow-sm mt-4">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h5 mb-3">Uploaded exclusion archives</h2>
                <ul class="mb-0 list-unstyled">
                    @foreach($process->exclusion_archives as $archive)
                    <li class="py-1 border-bottom">
                        <strong>{{ $archive['original_name'] ?? basename($archive['path'] ?? '') }}</strong>
                        <span class="text-muted ms-2">{{ isset($archive['size']) ? number_format((int) $archive['size'] / 1024, 1) . ' KB' : '' }}</span>
                        <span class="text-muted ms-2">{{ isset($archive['uploaded_at']) ? \Illuminate\Support\Carbon::parse($archive['uploaded_at'])->toDayDateTimeString() : '' }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', () => {
        const maxFiles = <?php echo (int) $maxFiles; ?>;
        const maxWorkbooks = maxFiles;
        const dropzone = document.getElementById('exclusion-dropzone');
        const fileInput = document.getElementById('exclusion-files');
        const helper = document.getElementById('exclusion-dropzone-helper');
        const baseHelperText = helper ? helper.textContent.trim() : '';
        const fileList = document.getElementById('exclusion-file-list');
        const errorContainer = document.getElementById('exclusion-errors');
        const form = document.getElementById('exclusion-upload-form');
        const clearButton = document.getElementById('exclusion-clear');
        const progressBlock = document.getElementById('exclusion-upload-progress-block');
        const progressTrack = document.getElementById('exclusion-upload-progress-track');
        const progressBar = document.getElementById('exclusion-upload-progress-bar');
        const progressLabel = document.getElementById('exclusion-upload-progress-label');
        const progressDetails = document.getElementById('exclusion-upload-progress-details');
        const progressMeta = document.getElementById('exclusion-upload-progress-meta');
        const selectedFiles = [];
        let loaderActive = false;
        let uploadQueueActive = false;
        const submitButton = form.querySelector('button[type="submit"]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const stagedUploads = @json($stagedUploads ?? []);
        const uploadSingleRoute = @json(route('process.exclusions.upload.single'));
        const destroyRouteTemplate = @json(route('process.exclusions.staged.destroy', ['token' => '__TOKEN__']));
        const progressStreamTemplate = @json(route('process.exclusions.progress.stream', ['token' => '__TOKEN__']));
        const progressPollTemplate = @json(route('process.exclusions.progress', ['token' => '__TOKEN__']));
        const exclusionDebugEnabled = true;

        const debugLog = (...args) => {
            if (!exclusionDebugEnabled) {
                return;
            }

            const line = ['[EXCLUSION_DEBUG]', new Date().toISOString(), ...args];
            console.log(...line);
            window.__exclusionDebugLog = window.__exclusionDebugLog || [];
            window.__exclusionDebugLog.push(line.map((item) => {
                if (typeof item === 'string') {
                    return item;
                }

                try {
                    return JSON.stringify(item);
                } catch (_error) {
                    return String(item);
                }
            }).join(' '));
        };

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

        const createLocalId = () => {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            return `${Date.now()}-${Math.random()}`;
        };

        const setProgress = (percentage, label, meta, options = {}) => {
            if (!progressBlock || !progressBar) {
                return;
            }
            const hideBar = options?.hideBar === true;
            progressBlock.classList.remove('d-none');
            progressTrack?.classList.toggle('d-none', hideBar);
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

        const getUploadedWorkbookCount = () => {
            return selectedFiles
                .filter((entry) => entry.status === 'uploaded')
                .reduce((sum, entry) => sum + Math.max(Number(entry.excelCount || 0), 0), 0);
        };

        const updateProgressDetails = () => {
            if (!progressDetails) {
                return;
            }

            const uploadedFiles = selectedFiles.filter((entry) => entry.status === 'uploaded');
            
            // Only pluralize if there is specifically more than 1 file uploaded, since it will be removed otherwise
            if (uploadedFiles.length > 0) {
                const workbookCount = getUploadedWorkbookCount();
                progressDetails.textContent = `${uploadedFiles.length} file(s) uploaded, ${workbookCount}/${maxWorkbooks} Excel workbook(s) received.`;
            } else {
                progressDetails.textContent = '';
            }
        };

        const updateDropzoneState = () => {
            if (!dropzone || !fileInput) {
                return;
            }

            const workbookLimitReached = getUploadedWorkbookCount() >= maxWorkbooks;
            const fileLimitReached = selectedFiles.length >= maxFiles;
            const shouldHide = workbookLimitReached || fileLimitReached;

            dropzone.classList.toggle('d-none', shouldHide);
            fileInput.disabled = shouldHide;
        };

        const updateOverallProgress = () => {
            const uploadable = selectedFiles.filter((entry) => entry.size > 0);
            if (!uploadable.length) {
                progressBlock?.classList.add('d-none');
                progressTrack?.classList.remove('d-none');
                if (progressDetails) {
                    progressDetails.textContent = '';
                }
                return;
            }

            const totalBytes = uploadable.reduce((sum, entry) => sum + entry.size, 0);
            const completedBytes = uploadable.reduce((sum, entry) => sum + Math.round((entry.progress / 100) * entry.size), 0);
            const percentage = totalBytes > 0 ? Math.round((completedBytes / totalBytes) * 100) : 0;
            const activeEntry = selectedFiles.find((entry) => entry.status === 'uploading');
            const hasPending = selectedFiles.some((entry) => entry.status === 'uploading' || entry.status === 'queued');
            const label = activeEntry ? `Uploading ${activeEntry.name}` : 'Uploaded exclusion files';
            setProgress(
                percentage,
                label,
                `${formatBytes(completedBytes)} / ${formatBytes(totalBytes)}`,
                { hideBar: !hasPending && percentage >= 100 }
            );
            updateProgressDetails();
        };

        const toggleSubmitState = () => {
            if (!submitButton) {
                return;
            }

            const hasUploaded = selectedFiles.some((entry) => entry.status === 'uploaded');
            const hasPending = selectedFiles.some((entry) => entry.status === 'uploading' || entry.status === 'queued');
            const isAnyValidating = selectedFiles.some((entry) => entry.status === 'uploaded' && entry.validationStatus !== 'ready' && entry.validationStatus !== 'failed');
            const hasFailed = selectedFiles.some((entry) => entry.status === 'error' || entry.validationStatus === 'failed');

            submitButton.disabled = loaderActive || uploadQueueActive || !hasUploaded || hasPending || isAnyValidating || hasFailed;
        };

        if (!dropzone || !fileInput || !form) {
            return;
        }

        toggleSubmitState();

        const renderList = () => {
            if (!fileList) {
                return;
            }

            if (!selectedFiles.length) {
                fileList.innerHTML = '<p class="mb-0 text-muted">No files selected yet.</p>';
                fileList.classList.add('text-muted');
                progressBlock?.classList.add('d-none');
                progressTrack?.classList.remove('d-none');
                if (progressDetails) {
                    progressDetails.textContent = '';
                }
                if (progressMeta) {
                    progressMeta.textContent = '';
                }
                if (progressLabel) {
                    progressLabel.textContent = 'Waiting to upload';
                }
                return;
            }

            fileList.classList.remove('text-muted');
            const list = document.createElement('ul');
            list.className = 'list-unstyled mb-0 process-selected-file-list';

            selectedFiles.forEach((file, index) => {
                const item = document.createElement('li');
                item.className = 'py-2 border-bottom';

                const row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-center gap-2';

                const details = document.createElement('span');
                const name = document.createElement('strong');
                name.textContent = file.name;

                const size = document.createElement('small');
                size.className = 'text-muted ms-2';
                size.textContent = (file.size / 1024).toFixed(1) + ' KB';

                details.appendChild(name);
                details.appendChild(size);

                const state = document.createElement('small');
                state.className = 'd-block text-muted';
                if (file.status === 'uploaded') {
                    const workbookCount = Number(file.excelCount || 0);
                    const baseLabel = workbookCount > 0
                        ? `Uploaded • ${workbookCount} Excel workbook(s) in this file`
                        : 'Uploaded';
                    if (file.validationStatus) {
                        const statusLabel = file.validationStatus === 'ready'
                            ? 'Validated'
                            : (file.validationStatus === 'failed' ? 'Validation failed' : 'Validating');
                        const heartbeat = file.validationHeartbeat ? ` • ${file.validationHeartbeat}` : '';
                        state.textContent = `${baseLabel} • ${statusLabel}${heartbeat}`;
                    } else {
                        state.textContent = baseLabel;
                    }
                } else if (file.status === 'uploading') {
                    state.textContent = `Uploading ${file.progress}%`;
                } else if (file.status === 'failed') {
                    state.textContent = file.error || 'Upload failed';
                } else {
                    state.textContent = 'Queued for upload';
                }
                details.appendChild(state);

                row.appendChild(details);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-link py-0 text-decoration-none shadow-none exclusion-remove-btn';
                remove.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"/><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>`;
                remove.disabled = file.status === 'uploading';
                remove.addEventListener('click', () => removeFile(index));
                row.appendChild(remove);

                item.appendChild(row);

                if (file.status === 'uploading') {
                    const progress = document.createElement('div');
                    progress.className = 'progress mt-2';
                    progress.style.height = '0.65rem';
                    const bar = document.createElement('div');
                    bar.className = 'progress-bar';
                    bar.style.width = `${file.progress}%`;
                    progress.appendChild(bar);
                    item.appendChild(progress);
                }

                list.appendChild(item);
            });

            fileList.innerHTML = '';
            fileList.appendChild(list);
            updateOverallProgress();
            updateDropzoneState();
            updateHelperText();
            toggleSubmitState();
        };

        const createTransfer = () => {
            if (typeof DataTransfer === 'undefined') {
                return null;
            }

            try {
                return new DataTransfer();
            } catch (error) {
                return null;
            }
        };

        const syncInputFiles = () => {
            const transfer = createTransfer();
            if (!transfer) {
                return;
            }

            selectedFiles.forEach((file) => transfer.items.add(file));
            fileInput.files = transfer.files;
        };

        const updateHelperText = (limitMessage = null) => {
            if (!helper) {
                return;
            }

            if (limitMessage) {
                helper.textContent = limitMessage;
                return;
            }

            if (!selectedFiles.length) {
                helper.textContent = baseHelperText;
                return;
            }

            const names = selectedFiles.map((file) => file.name).join(', ');
            const remaining = Math.max(maxFiles - selectedFiles.length, 0);
            const workbookCount = getUploadedWorkbookCount();
            const status = selectedFiles.length + '/' + maxFiles + ' file(s) selected';
            helper.textContent = remaining > 0 ?
                status + ': ' + names + `. ${workbookCount}/${maxWorkbooks} Excel workbook(s) received. You can add ` + remaining + ' more.' :
                status + ': ' + names + `. ${workbookCount}/${maxWorkbooks} Excel workbook(s) received.`;
        };

        const showError = (messages) => {
            if (!errorContainer) {
                alert(messages.join('\n'));
                return;
            }

            const block = document.createElement('div');
            block.className = 'alert alert-danger';
            block.setAttribute('role', 'alert');
            const list = document.createElement('ul');
            list.className = 'mb-0';

            (Array.isArray(messages) ? messages : [messages]).forEach((message) => {
                const item = document.createElement('li');
                item.textContent = message;
                list.appendChild(item);
            });

            block.appendChild(list);
            errorContainer.innerHTML = '';
            errorContainer.appendChild(block);
            block.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        };

        const clearErrors = () => {
            if (errorContainer) {
                errorContainer.innerHTML = '';
            }
        };

        const addFiles = (files) => {
            if (!files?.length) {
                return;
            }

            debugLog('addFiles', { count: files.length });

            clearErrors();

            if (getUploadedWorkbookCount() >= maxWorkbooks) {
                showError([`${maxWorkbooks} Excel workbooks already received. Remove one before adding another file.`]);
                updateHelperText(`${maxWorkbooks} Excel workbooks already received.`);
                return;
            }

            for (const file of files) {
                if (selectedFiles.length >= maxFiles) {
                    updateHelperText('You have reached the limit of ' + maxFiles + ' files.');
                    break;
                }

                if (!file.name.toLowerCase().endsWith('.xlsx')) {
                    showError(['Only .xlsx workbooks are allowed for exclusions.']);
                    continue;
                }

                const exists = selectedFiles.some((current) => current.name === file.name && current.size === file.size);
                if (exists) {
                    continue;
                }

                selectedFiles.push({
                    localId: createLocalId(),
                    file,
                    name: file.name,
                    size: file.size,
                    progress: 0,
                    status: 'queued',
                    stagedId: null,
                    excelCount: 0,
                    error: null,
                });
            }

            updateHelperText();

            renderList();
            toggleSubmitState();
            processQueue();
        };

        const removeFile = async (index) => {
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }

            const [entry] = selectedFiles.splice(index, 1);

            if (entry?.stagedId) {
                const destroyRoute = destroyRouteTemplate.replace('__TOKEN__', encodeURIComponent(entry.stagedId));
                await fetch(destroyRoute, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    credentials: 'same-origin',
                }).catch(() => null);
            }

            updateHelperText();
            fileInput.value = ''; // reset input to allow same file reselect after delete
            renderList();
            toggleSubmitState();
        };

        const uploadEntry = async (entry) => {
            debugLog('uploadEntry:start', { name: entry.name, size: entry.size });
            entry.status = 'uploading';
            entry.error = null;
            entry.progress = 0;
            renderList();
            toggleSubmitState();

            const requestHeaders = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            };

            const payload = new FormData();
            payload.append('exclusion', entry.file, entry.name);

            const controller = new AbortController();
            const timeoutMs = 20000;
            const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

            let uploadResponse = null;
            try {
                uploadResponse = await fetch(uploadSingleRoute, {
                    method: 'POST',
                    body: payload,
                    headers: requestHeaders,
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    debugLog('uploadEntry:timeout', { name: entry.name, timeoutMs });
                    throw new Error('Upload request timed out. Please retry.');
                }

                throw error;
            } finally {
                window.clearTimeout(timeoutId);
            }

            const finishJson = await uploadResponse.json().catch(() => null);
            debugLog('uploadEntry:response', { ok: uploadResponse.ok, status: uploadResponse.status, body: finishJson });
            if (!uploadResponse.ok || !finishJson?.file?.id) {
                throw new Error(finishJson?.message || 'Unable to upload exclusion file.');
            }

            entry.status = 'uploaded';
            entry.progress = 100;
            entry.stagedId = finishJson.file.id;
            entry.excelCount = Number(finishJson?.file?.excel_count || 0);
            entry.file = null;
            entry.validationStatus = 'validating';
            entry.validationHeartbeat = '';
            renderList();
            startValidationStream(entry);
        };

        const startValidationStream = (entry) => {
            if (!entry?.stagedId) {
                return;
            }

            debugLog('validation:stream:start', { stagedId: entry.stagedId, file: entry.name });

            const streamUrl = progressStreamTemplate.replace('__TOKEN__', encodeURIComponent(entry.stagedId));
            const pollUrl = progressPollTemplate.replace('__TOKEN__', encodeURIComponent(entry.stagedId));

            if (typeof window.EventSource === 'undefined') {
                return startValidationPoll(entry, pollUrl);
            }

            let source = null;
            try {
                source = new EventSource(streamUrl, { withCredentials: true });
            } catch (_error) {
                return startValidationPoll(entry, pollUrl);
            }

            const handlePayload = (data) => {
                if (!data) {
                    return;
                }
                debugLog('validation:payload', { stagedId: entry.stagedId, status: data.status, progress: data.progress, message: data.message, error: data.error });
                entry.validationStatus = data.status || entry.validationStatus;
                entry.validationMessage = data.message || entry.validationMessage;
                entry.validationProgress = typeof data.progress === 'number' ? data.progress : entry.validationProgress;
                entry.validationHeartbeat = buildHeartbeat(data.last_updated_at);
                if (entry.validationStatus === 'failed' && Array.isArray(data.errors) && data.errors.length) {
                    entry.error = data.errors[0];
                } else if (entry.validationStatus === 'failed' && data.error) {
                    entry.error = data.error;
                }
                if (entry.validationStatus === 'failed') {
                    showError([entry.error || data.message || 'Validation failed.']);
                }
                renderList();
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
                debugLog('validation:stream:error', { stagedId: entry.stagedId });
                source?.close();
                source = null;
                startValidationPoll(entry, pollUrl);
            };
        };

        const startValidationPoll = (entry, url) => {
            let pollTimer = null;
            const poll = async () => {
                try {
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
                    if (!resp.ok) return;
                    const data = await resp.json();
                    entry.validationStatus = data.status || entry.validationStatus;
                    entry.validationMessage = data.message || entry.validationMessage;
                    entry.validationProgress = typeof data.progress === 'number' ? data.progress : entry.validationProgress;
                    entry.validationHeartbeat = buildHeartbeat(data.last_updated_at);
                    if (entry.validationStatus === 'failed' && Array.isArray(data.errors) && data.errors.length) {
                        entry.error = data.errors[0];
                    } else if (entry.validationStatus === 'failed' && data.error) {
                        entry.error = data.error;
                    }
                    if (entry.validationStatus === 'failed') {
                        showError([entry.error || data.message || 'Validation failed.']);
                    }
                    renderList();

                    if (['ready', 'failed', 'canceled'].includes(entry.validationStatus)) {
                        clearInterval(pollTimer);
                    }
                } catch (_error) {}
            };

            poll();
            pollTimer = setInterval(poll, 2000);
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

        const processQueue = async () => {
            if (uploadQueueActive) {
                return;
            }

            uploadQueueActive = true;
            toggleSubmitState();

            try {
                while (true) {
                    const next = selectedFiles.find((entry) => entry.status === 'queued');
                    if (!next) {
                        break;
                    }

                    try {
                        await uploadEntry(next);
                    } catch (error) {
                        next.status = 'failed';
                        next.error = error instanceof Error ? error.message : 'Upload failed';
                        renderList();
                        showError([next.error]);
                    }
                }
            } finally {
                uploadQueueActive = false;
                toggleSubmitState();
                updateOverallProgress();
            }
        };

        dropzone.addEventListener('click', (event) => {
            // The label inside the dropzone already triggers the native file picker
            // when clicked. If the click originated on the label (or its children),
            // don't call fileInput.click() again — that causes two pickers to open.
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
            addFiles(event.dataTransfer?.files || []);
        });

        fileInput.addEventListener('change', () => {
            addFiles(fileInput.files);
        });

        const clearLocalState = () => {
            selectedFiles.length = 0;
            renderList();
            updateHelperText();
            toggleSubmitState();
            clearErrors();
            progressBlock?.classList.add('d-none');
            updateDropzoneState();
        };

        const clearAll = (deleteRemote = true) => {
            if (!deleteRemote) {
                clearLocalState();
                return;
            }

            const uploadedEntries = selectedFiles.filter((entry) => entry.stagedId);
            Promise.all(uploadedEntries.map((entry) => {
                const destroyRoute = destroyRouteTemplate.replace('__TOKEN__', encodeURIComponent(entry.stagedId));
                return fetch(destroyRoute, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    credentials: 'same-origin',
                }).catch(() => null);
            })).finally(() => {
                clearLocalState();
            });
        };

        clearButton?.addEventListener('click', (event) => {
            event.preventDefault();
            clearAll();
        });

        const renderNotice = (message, variant = 'success') => {
            if (!errorContainer) {
                alert(message);
                return;
            }

            const block = document.createElement('div');
            block.className = 'alert alert-' + (variant === 'info' ? 'info' : 'success');
            block.setAttribute('role', 'alert');
            block.textContent = message;
            errorContainer.innerHTML = '';
            errorContainer.appendChild(block);
            block.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!selectedFiles.length) {
                showError(['Please add at least one exclusion file before submitting.']);
                return;
            }


            if (loaderActive) {
                return;
            }

            if (uploadQueueActive) {
                showError(['Please wait until all exclusion files finish uploading.']);
                return;
            }

            loaderActive = true;
            clearErrors();

            if (submitButton) {
                submitButton.disabled = true;
            }

            const uploadedEntries = selectedFiles.filter((entry) => entry.status === 'uploaded' && entry.stagedId);
            if (!uploadedEntries.length) {
                loaderActive = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
                showError(['Please upload at least one exclusion file before applying exclusions.']);
                return;
            }

            const formData = new FormData();
            uploadedEntries.forEach((entry) => formData.append('staged_upload_ids[]', entry.stagedId));

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok) {
                    loaderActive = false;
                    if (submitButton) {
                        submitButton.disabled = false;
                    }

                    if (response.status === 422 && payload?.errors) {
                        const messages = Object.values(payload.errors).flat();
                        showError(messages);
                    } else {
                        showError(payload?.message ? [payload.message] : ['Unable to process the exclusion files. Please try again.']);
                    }
                    return;
                }

                // Completed request; leave loader visible until overall process reaches ready
                loaderActive = false;

                if (payload?.message) {
                    renderNotice(payload.message, payload.status === 'info' ? 'info' : 'success');
                } else {
                    clearErrors();
                }

                if (payload?.redirect_url) {
                    window.setTimeout(() => {
                        window.location.href = payload.redirect_url;
                    }, 250);
                }


                if (submitButton) {
                    submitButton.disabled = false;
                }

                clearAll(false);
            } catch (error) {
                    debugLog('submit:error', { message: error?.message || 'unknown error' });
                loaderActive = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
                showError(['Unable to process the exclusion files. Please try again.']);
            }
        });

        stagedUploads.forEach((entry) => {
            selectedFiles.push({
                localId: entry.id,
                file: null,
                name: entry.name,
                size: Number(entry.size || 0),
                progress: 100,
                status: 'uploaded',
                stagedId: entry.id,
                excelCount: Number(entry.excel_count || 1),
                validationStatus: 'ready',
                validationHeartbeat: 'Active',
                error: null,
            });
        });

        if (selectedFiles.length === 0) {
            clearAll();
        } else {
            renderList();
            updateHelperText();
            toggleSubmitState();
            updateDropzoneState();
        }
    });
</script>
@endpush
@endsection