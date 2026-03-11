@extends('layouts.admin')

@section('title', 'Exclusions')

@section('loaderAutoRedirect', true)

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
                                <p class="text-muted mb-0">Upload up to {{ $maxFiles }} ZIP archives (each with a single Excel workbook) that list the identifiers you want to remove from the master list.</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                <a href="#" class="btn btn-outline-secondary" data-loader-off="true" onclick="history.back(); return false;">Back</a>
                                <button type="button" class="btn btn-outline-secondary" id="exclusion-clear">Clear selected</button>
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
                        <input type="file" class="visually-hidden" id="exclusion-files" name="exclusions[]" accept=".zip" multiple>
                        <label for="exclusion-files" class="process-dropzone-content text-center" tabindex="0" role="button">
                            <p class="process-dropzone-title mb-1">Drag and drop or click to add ZIP files</p>
                            <p class="text-muted mb-0" id="exclusion-dropzone-helper">
                                You can queue up to {{ $maxFiles }} .zip files. Each ZIP must include exactly one Excel workbook.
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
                            <li><strong>Import the three exclusion workbooks:</strong> Add each exclusion file (up to three) to the form. Each file should be a ZIP containing exactly one Excel workbook with the standard header row (for example, <code>CUSTOMER_REF</code> and <code>ACCOUNT_NUM</code>).</li>
                            <li><strong>Remove matches from the master list:</strong> After uploading, you can either let the system apply exclusions when you press <em>Apply exclusions</em>, or you may perform the matching yourself offline before uploading by using Excel functions such as <code>VLOOKUP</code>, <code>XLOOKUP</code> or conditional filters. Matching is performed when either <code>CUSTOMER_REF</code> or <code>ACCOUNT_NUM</code> appears in any exclusion workbook.</li>
                        </ol>
                        <ul class="mb-0">
                            <li>Each ZIP must contain exactly one workbook with the standard header row (for example, <strong>CUSTOMER_REF</strong> and <strong>ACCOUNT_NUM</strong>).</li>
                            <li>You may add the three ZIP archives sequentially; they will appear in the list before you submit.</li>
                            <li>When you press <strong>Apply exclusions</strong>, the system will extract each workbook, merge the rows, and remove any matching rows from the master list.</li>
                            <li>Only ZIP archives containing Microsoft Excel (.xlsx) files are supported for this workflow.</li>
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
        const startRoute = @json(route('process.exclusions.chunks.start'));
        const partRoute = @json(route('process.exclusions.chunks.part'));
        const finishRoute = @json(route('process.exclusions.chunks.finish'));
        const destroyRouteTemplate = @json(route('process.exclusions.staged.destroy', ['token' => '__TOKEN__']));

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

            const uploadedFiles = selectedFiles.filter((entry) => entry.status === 'uploaded').length;
            const workbookCount = getUploadedWorkbookCount();
            progressDetails.textContent = `${uploadedFiles} ZIP uploaded, ${workbookCount}/${maxWorkbooks} Excel workbook(s) received.`;
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
            submitButton.disabled = loaderActive || uploadQueueActive || !hasUploaded || hasPending;
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
                    state.textContent = workbookCount > 0
                        ? `Uploaded • ${workbookCount} Excel workbook(s) in this ZIP`
                        : 'Uploaded';
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
                remove.className = 'btn btn-sm btn-outline-danger';
                remove.textContent = 'Remove';
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

                if (!file.name.toLowerCase().endsWith('.zip')) {
                    showError(['Only .zip archives are allowed for exclusions.']);
                    continue;
                }

                const exists = selectedFiles.some((current) => current.name === file.name && current.size === file.size);
                if (exists) {
                    continue;
                }

                selectedFiles.push({
                    localId: crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`,
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
            renderList();
            toggleSubmitState();
        };

        const uploadEntry = async (entry) => {
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

            const startPayload = new FormData();
            startPayload.append('file_name', entry.name);
            startPayload.append('file_size', String(entry.size));
            startPayload.append('mime_type', entry.file?.type || 'application/zip');

            const startResponse = await fetch(startRoute, {
                method: 'POST',
                body: startPayload,
                headers: requestHeaders,
                credentials: 'same-origin',
            });
            const startJson = await startResponse.json().catch(() => null);
            if (!startResponse.ok || !startJson?.upload_token) {
                throw new Error(startJson?.message || 'Unable to start exclusion upload.');
            }

            const uploadToken = startJson.upload_token;
            const chunkSize = Number(startJson.chunk_size || (2 * 1024 * 1024));
            const totalChunks = Math.max(1, Math.ceil(entry.size / chunkSize));

            for (let index = 0; index < totalChunks; index += 1) {
                const start = index * chunkSize;
                const end = Math.min(start + chunkSize, entry.size);
                const chunk = entry.file.slice(start, end);
                const payload = new FormData();
                payload.append('upload_token', uploadToken);
                payload.append('chunk_index', String(index));
                payload.append('chunk', chunk, `${entry.name}.part${index}`);

                const chunkResponse = await fetch(partRoute, {
                    method: 'POST',
                    body: payload,
                    headers: requestHeaders,
                    credentials: 'same-origin',
                });
                const chunkJson = await chunkResponse.json().catch(() => null);
                if (!chunkResponse.ok) {
                    throw new Error(chunkJson?.message || 'Unable to upload a chunk.');
                }

                entry.progress = Math.min(99, Math.round(((index + 1) / totalChunks) * 100));
                renderList();
            }

            const finishPayload = new FormData();
            finishPayload.append('upload_token', uploadToken);
            finishPayload.append('total_chunks', String(totalChunks));

            const finishResponse = await fetch(finishRoute, {
                method: 'POST',
                body: finishPayload,
                headers: requestHeaders,
                credentials: 'same-origin',
            });
            const finishJson = await finishResponse.json().catch(() => null);
            if (!finishResponse.ok || !finishJson?.file?.id) {
                throw new Error(finishJson?.message || 'Unable to finalize exclusion upload.');
            }

            entry.status = 'uploaded';
            entry.progress = 100;
            entry.stagedId = finishJson.file.id;
            entry.excelCount = Number(finishJson?.file?.excel_count || 0);
            entry.file = null;
            renderList();
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

            // Show simplified global loader (polling already active via app.js)
            window.CDDSLoader?.show?.();

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
                    window.CDDSLoader?.hide?.();
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
                    // Defer redirect until final status (ready/failed) is reported
                    const onFinal = () => {
                        document.removeEventListener('cdds:loader-final', onFinal);
                        window.setTimeout(() => {
                            window.location.href = payload.redirect_url;
                        }, 400);
                    };
                    document.addEventListener('cdds:loader-final', onFinal);
                }


                if (submitButton) {
                    submitButton.disabled = false;
                }

                clearAll(false);
            } catch (error) {
                    window.CDDSLoader?.hide?.();
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