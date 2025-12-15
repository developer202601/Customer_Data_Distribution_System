@extends('layouts.admin')
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
                                @if(isset($process) && ! session('hide_dataset_info'))
                                <p class="text-muted mb-0 mt-2">Active dataset token: <strong>{{ $process->token }}</strong> ({{ number_format((int) $process->row_count) }} rows)</p>
                                @endif
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
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const maxFiles = <?php echo (int) $maxFiles; ?>;
        const dropzone = document.getElementById('exclusion-dropzone');
        const fileInput = document.getElementById('exclusion-files');
        const helper = document.getElementById('exclusion-dropzone-helper');
        const baseHelperText = helper ? helper.textContent.trim() : '';
        const fileList = document.getElementById('exclusion-file-list');
        const errorContainer = document.getElementById('exclusion-errors');
        const form = document.getElementById('exclusion-upload-form');
        const clearButton = document.getElementById('exclusion-clear');
        const selectedFiles = [];
        let loaderActive = false;
        const submitButton = form.querySelector('button[type="submit"]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const toggleSubmitState = () => {
            if (!submitButton) {
                return;
            }

            submitButton.disabled = selectedFiles.length === 0;
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
                item.className = 'd-flex justify-content-between align-items-center gap-2 py-1 border-bottom';

                const details = document.createElement('span');
                const name = document.createElement('strong');
                name.textContent = file.name;

                const size = document.createElement('small');
                size.className = 'text-muted ms-2';
                size.textContent = (file.size / 1024).toFixed(1) + ' KB';

                details.appendChild(name);
                details.appendChild(size);

                item.appendChild(details);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-sm btn-outline-danger';
                remove.textContent = 'Remove';
                remove.addEventListener('click', () => removeFile(index));
                item.appendChild(remove);

                list.appendChild(item);
            });

            fileList.innerHTML = '';
            fileList.appendChild(list);
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
            const status = selectedFiles.length + '/' + maxFiles + ' file(s) selected';
            helper.textContent = remaining > 0 ?
                status + ': ' + names + '. You can add ' + remaining + ' more.' :
                status + ': ' + names + '.';
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

                selectedFiles.push(file);
            }

            updateHelperText();

            syncInputFiles();
            renderList();
            toggleSubmitState();
        };

        const removeFile = (index) => {
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }

            selectedFiles.splice(index, 1);
            updateHelperText();
            syncInputFiles();
            renderList();
            toggleSubmitState();
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

        const clearAll = () => {
            selectedFiles.length = 0;
            syncInputFiles();
            renderList();
            updateHelperText();
            toggleSubmitState();
            clearErrors();
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

            loaderActive = true;
            clearErrors();

            // Show simplified global loader (polling already active via app.js)
            window.CDDSLoader?.show?.();

            if (submitButton) {
                submitButton.disabled = true;
            }

            const formData = new FormData(form);

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

                clearAll();
            } catch (error) {
                    window.CDDSLoader?.hide?.();
                loaderActive = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
                showError(['Unable to process the exclusion files. Please try again.']);
            }
        });

        clearAll();
    });
</script>
@endpush
@endsection