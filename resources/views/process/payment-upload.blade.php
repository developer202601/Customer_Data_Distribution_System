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
        <form id="payment-upload-form" action="{{ route('payments.update') }}" method="post" enctype="multipart/form-data" data-loader-off="1">
            @csrf
            <div class="card process-upload-card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h1 class="process-upload-title mb-1">Payment Files Upload</h1>
                                <p class="text-muted mb-0">Upload a Microsoft Excel (.xlsx) workbook with exact headers: <code>ACCOUNT_NUM</code>, <code>ACCOUNT_PAYMENT_MNY</code>, and <code>DATASET_MONTH</code>.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Back</a>
                            <a href="{{ route('payments.history') }}" class="btn btn-outline-secondary px-4" data-loader-off="1">Upload history</a>
                            <button type="submit" class="btn btn-dark px-4 d-none" id="payment-upload-submit">Submit</button>
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
                            <p class="text-muted mb-0" id="payment-dropzone-helper">Upload your Payment Excel workbook (.xlsx) with exact headers ACCOUNT_NUM, ACCOUNT_PAYMENT_MNY, and DATASET_MONTH.</p>
                        </label>
                    </div>

                    <div id="payment-errors" class="mt-4">
                        <div id="payment-error-alert" class="alert alert-danger d-none" role="alert"></div>
                        <div id="payment-success-alert" class="alert alert-success d-none" role="alert"></div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function() {
    const pageLoader = document.getElementById('page-loader');
    if (pageLoader) {
        pageLoader.style.display = 'none';
    }
})();
</script>
<script nonce="{{ $cspNonce ?? '' }}">
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
    const errorAlert = document.getElementById('payment-error-alert');
    const successAlert = document.getElementById('payment-success-alert');

    if (!fileInput || !submitBtn) {
        return;
    }

    function setProgress(percentage, label, meta) {
        if (!progressBlock || !progressBar) {
            return;
        }

        progressBlock.classList.remove('d-none');
        progressBlock.style.position = 'relative';
        progressBlock.style.zIndex = '2100';
        progressBar.style.width = `${percentage}%`;
        progressBar.textContent = `${percentage}%`;
        progressBar.setAttribute('aria-valuenow', String(percentage));
        if (progressLabel) {
            progressLabel.textContent = label;
        }
        if (progressMeta) {
            progressMeta.textContent = meta;
        }

        const pageLoader = document.getElementById('page-loader');
        if (pageLoader) {
            pageLoader.style.display = 'none';
        }
    }

    function hideProgress() {
        progressBlock?.classList.add('d-none');
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
        }
        const pageLoader = document.getElementById('page-loader');
        if (pageLoader) {
            pageLoader.style.display = '';
        }
    }

    function showError(message) {
        if (errorAlert) {
            errorAlert.textContent = message;
            errorAlert.classList.remove('d-none');
        }
        if (successAlert) {
            successAlert.classList.add('d-none');
        }
    }

    function showSuccess(message) {
        if (successAlert) {
            successAlert.textContent = message;
            successAlert.classList.remove('d-none');
        }
        if (errorAlert) {
            errorAlert.classList.add('d-none');
        }
    }

    function resetForm() {
        if (form) {
            form.reset();
        }
        if (submitBtn) {
            submitBtn.classList.add('d-none');
            submitBtn.disabled = true;
        }
        hideProgress();
        if (dropzoneHelper) {
            dropzoneHelper.textContent = 'Upload your Payment Excel workbook (.xlsx) with exact headers ACCOUNT_NUM, ACCOUNT_PAYMENT_MNY, and DATASET_MONTH.';
        }
        if (errorAlert) {
            errorAlert.classList.add('d-none');
        }
        if (successAlert) {
            successAlert.classList.add('d-none');
        }
    }

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            submitBtn.classList.remove('d-none');
            submitBtn.disabled = false;
            if (dropzoneHelper) {
                dropzoneHelper.textContent = this.files[0].name;
            }
        } else {
            submitBtn.classList.add('d-none');
            submitBtn.disabled = true;
        }
    });

    if (dropzone) {
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('is-dragover');
        });

        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('is-dragover');
        });

        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('is-dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const file = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;
            if (!file) {
                return;
            }

            const formData = new FormData();
            formData.append('upload', file);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

            submitBtn.disabled = true;
            setProgress(0, 'Uploading payment file…', 'Please wait…');
            if (errorAlert) errorAlert.classList.add('d-none');
            if (successAlert) successAlert.classList.add('d-none');

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                },
            })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok || data.status !== 'ok') {
                    throw new Error(data.message || 'Upload failed. Please try again.');
                }
                return data;
            })
            .then((data) => {
                const token = data.token;
                if (!token) {
                    throw new Error('Upload did not return a processing token.');
                }
                startProgressStream(token);
            })
            .catch((error) => {
                showError(error.message || 'Upload failed. Please try again.');
                submitBtn.disabled = false;
                hideProgress();
            });
        });
    }

    function startProgressStream(token) {
        const streamUrl = @json(route('payments.progress.stream', ['token' => '__TOKEN__'])).replace('__TOKEN__', encodeURIComponent(token));

        if (typeof window.EventSource === 'undefined') {
            startPolling(token);
            return;
        }

        const source = new EventSource(streamUrl);

        source.addEventListener('error', () => {
            source.close();
        });

        source.addEventListener('message', (event) => {
            try {
                const payload = JSON.parse(event.data);
                handleProgressPayload(payload, token, source);
            } catch (e) {
                console.error('Failed to parse payment progress payload', e);
            }
        });

        source.addEventListener('heartbeat', () => {
            const cached = fetch(@json(route('payments.progress', ['token' => '__TOKEN__'])).replace('__TOKEN__', encodeURIComponent(token)))
                .then(res => res.json())
            .then((payload) => {
                handleProgressPayload(payload, token, source);
            }).catch(() => {});
        });
    }

    function startPolling(token) {
        const pollUrl = @json(route('payments.progress', ['token' => '__TOKEN__'])).replace('__TOKEN__', encodeURIComponent(token));
        let lastStatus = null;

        const interval = setInterval(() => {
            fetch(pollUrl, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then((payload) => {
                    handleProgressPayload(payload, token, null, interval);
                })
                .catch(() => {
                    clearInterval(interval);
                    showError('Progress tracking stopped. Please refresh the page to check status.');
                });
        }, 2000);
    }

    function handleProgressPayload(payload, token, source, interval) {
        const status = payload.status || 'processing';
        const progress = typeof payload.progress === 'number' ? payload.progress : 0;
        const message = payload.message || 'Processing…';
        const processedRows = typeof payload.processed_rows === 'number' ? payload.processed_rows : null;
        const totalRows = typeof payload.total_rows === 'number' ? payload.total_rows : null;
        const matched = typeof payload.matched === 'number' ? payload.matched : null;
        const updated = typeof payload.updated === 'number' ? payload.updated : null;
        const notFound = typeof payload.not_found === 'number' ? payload.not_found : null;
        const etaSeconds = typeof payload.eta_seconds === 'number' ? payload.eta_seconds : null;
        const error = payload.error || null;

        let meta = '';
        if (processedRows !== null && totalRows !== null && totalRows > 0) {
            meta = `${processedRows} / ${totalRows} rows`;
        } else if (processedRows !== null) {
            meta = `${processedRows} rows processed`;
        }

        if (etaSeconds !== null && etaSeconds > 0 && status === 'processing') {
            const minutes = Math.floor(etaSeconds / 60);
            const seconds = etaSeconds % 60;
            meta += (meta ? ' • ' : '') + `ETA ${minutes}m ${seconds}s`;
        }

        if (matched !== null && updated !== null && notFound !== null && status === 'complete') {
            meta = `Matched: ${matched} • Updated: ${updated} • Not found: ${notFound}`;
        }

        setProgress(progress, message, meta);

        if (status === 'complete') {
            if (source) {
                source.close();
            }
            if (interval) {
                clearInterval(interval);
            }
            showSuccess(message || 'Payment upload completed successfully.');
            submitBtn.disabled = false;
            if (clearBtn) clearBtn.classList.remove('d-none');
        } else if (status === 'failed') {
            if (source) {
                source.close();
            }
            if (interval) {
                clearInterval(interval);
            }
            showError(error || message || 'Payment processing failed.');
            submitBtn.disabled = false;
            if (clearBtn) clearBtn.classList.remove('d-none');
        }
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


