@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
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
        <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <strong>Upload Exclusion Files</strong>
                <p class="mb-0">Attach up to {{ $maxFiles }} Excel workbooks that describe the rows you want to exclude from the master dataset.</p>
            </div>
            <a href="{{ route('process.upload.preview') }}" class="btn btn-outline-secondary">Back to preview</a>
        </div>

        <form id="exclusion-upload-form"
            action="{{ route('process.exclusions.store') }}"
            method="post"
            enctype="multipart/form-data"
            data-loader-off="true">
            @csrf
            <div class="card process-upload-card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h1 class="process-upload-title mb-1">Upload exclusion sheets</h1>
                                <p class="text-muted mb-0">Select up to {{ $maxFiles }} Microsoft Excel (.xlsx) workbooks that contain the identifiers you want to remove from the master list.</p>
                                @if($filename)
                                <p class="text-muted mb-0 mt-2">Active dataset: <strong>{{ $filename }}</strong> ({{ number_format($totalRows) }} rows)</p>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="text-muted mb-1">You can add files one at a time or all at once.</p>
                            <button type="submit" class="btn btn-dark px-4">Apply exclusions</button>
                        </div>
                    </div>

                    <div id="exclusion-errors" class="mt-4">
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
                        <div class="process-dropzone-content text-center">
                            <p class="process-dropzone-title mb-1">Drag and drop or click to add Excel files</p>
                            <p class="text-muted mb-0" id="exclusion-dropzone-helper">
                                You can queue up to {{ $maxFiles }} .xlsx files. They will be matched row-by-row against the master list.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h2 class="process-guidelines-title h5">Selected files</h2>
                        <div id="exclusion-file-list" class="process-selected-files text-muted">
                            <p class="mb-0">No files selected yet.</p>
                        </div>
                    </div>

                    <div class="process-guidelines mt-4">
                        <h2 class="process-guidelines-title">Exclusion guidelines</h2>
                        <ul class="mb-0">
                            <li>Each workbook must include a header row with the standard column set (for example, CUSTOMER_REF and ACCOUNT_NUM).</li>
                            <li>You may add the three files sequentially; they will appear in the list before you submit.</li>
                            <li>As soon as you submit, the system will merge the uploaded sheets and remove any matching rows from the master list.</li>
                            <li>Matching occurs on both <strong>CUSTOMER_REF</strong> and <strong>ACCOUNT_NUM</strong>. If either value matches, the row will be excluded.</li>
                            <li>Only Microsoft Excel (.xlsx) files are supported for the exclusion workflow.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const maxFiles = {{ (int) $maxFiles }};
        const dropzone = document.getElementById('exclusion-dropzone');
        const fileInput = document.getElementById('exclusion-files');
        const helper = document.getElementById('exclusion-dropzone-helper');
        const fileList = document.getElementById('exclusion-file-list');
        const errorContainer = document.getElementById('exclusion-errors');
        const form = document.getElementById('exclusion-upload-form');
        const selectedFiles = [];

        if (!dropzone || !fileInput || !form) {
            return;
        }

        const renderList = () => {
            if (!fileList) {
                return;
            }

            if (!selectedFiles.length) {
                fileList.innerHTML = '<p class="mb-0 text-muted">No files selected yet.</p>';
                return;
            }

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

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn btn-link text-danger p-0';
                removeButton.dataset.removeIndex = String(index);
                removeButton.textContent = 'Remove';

                item.appendChild(details);
                item.appendChild(removeButton);
                list.appendChild(item);
            });

            fileList.innerHTML = '';
            fileList.appendChild(list);
        };

        const syncInputFiles = () => {
            const transfer = new DataTransfer();
            selectedFiles.forEach((file) => transfer.items.add(file));
            fileInput.files = transfer.files;
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
            block.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
                    helper.textContent = 'You have reached the limit of ' + maxFiles + ' files.';
                    break;
                }

                if (!file.name.toLowerCase().endsWith('.xlsx')) {
                    showError(['Only .xlsx files are allowed for exclusions.']);
                    continue;
                }

                const exists = selectedFiles.some((current) => current.name === file.name && current.size === file.size);
                if (exists) {
                    continue;
                }

                selectedFiles.push(file);
            }

            if (selectedFiles.length < maxFiles) {
                helper.textContent = 'You can add ' + (maxFiles - selectedFiles.length) + ' more file(s).';
            }

            syncInputFiles();
            renderList();
        };

        const removeFile = (index) => {
            if (index < 0 || index >= selectedFiles.length) {
                return;
            }

            selectedFiles.splice(index, 1);
            helper.textContent = 'You can add ' + (maxFiles - selectedFiles.length) + ' more file(s).';
            syncInputFiles();
            renderList();
        };

        dropzone.addEventListener('click', () => fileInput.click());

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

        fileList.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-index]');
            if (!button) {
                return;
            }
            const index = Number(button.dataset.removeIndex);
            removeFile(index);
        });

        form.addEventListener('submit', (event) => {
            if (!selectedFiles.length) {
                event.preventDefault();
                showError(['Please add at least one exclusion file before submitting.']);
                return;
            }
        });

        renderList();
    });
</script>
@endpush
@endsection
