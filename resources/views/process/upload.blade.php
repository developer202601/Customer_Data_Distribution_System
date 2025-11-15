@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step active"></span>
    <span class="process-step"></span>
    <span class="process-step"></span>
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
        <form action="{{ route('process.upload.store') }}" method="post" enctype="multipart/form-data">
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
                                <p class="text-muted mb-0">We currently accept Microsoft Excel (.xlsx) files only.</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Back</a>
                        <button type="submit" class="btn btn-dark px-4">Submit</button>
                    </div>

                    @if($errors->any())
                    <div class="alert alert-danger mt-4 mb-0" role="alert">
                        <p class="mb-2 fw-semibold">Please resolve the following issues before continuing:</p>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="process-dropzone mt-4" id="process-dropzone">
                        <input type="file" class="visually-hidden" id="upload" name="upload" accept=".xlsx">
                        <div class="process-dropzone-content text-center">
                            <p class="process-dropzone-title mb-1">Drag and drop file or click to browse</p>
                            <p class="text-muted mb-0" id="process-dropzone-helper">Only .xlsx files are supported.</p>
                        </div>
                    </div>
                    <!-- @error('upload')
                    <small class="text-danger d-block mt-2">{{ $message }}</small>
                    @enderror -->

                    <div class="process-guidelines mt-4">
                        <h2 class="process-guidelines-title">File requirements</h2>
                        <ul class="mb-0">
                            <li>Upload a single Microsoft Excel workbook with the header row shown in the data dictionary.</li>
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
        const dropzone = document.getElementById('process-dropzone');
        const fileInput = document.getElementById('upload');
        const helper = document.getElementById('process-dropzone-helper');

        const updateHelper = (file) => {
            helper.textContent = file ? file.name : 'Only .xlsx files are supported.';
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

            if (!event.dataTransfer?.files?.length) {
                return;
            }

            const [file] = event.dataTransfer.files;

            if (file && file.name.toLowerCase().endsWith('.xlsx')) {
                fileInput.files = event.dataTransfer.files;
                updateHelper(file);
            } else {
                updateHelper(null);
                alert('Please upload an Excel .xlsx file.');
            }
        });

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];

            if (file && file.name.toLowerCase().endsWith('.xlsx')) {
                updateHelper(file);
            } else {
                fileInput.value = '';
                updateHelper(null);
                if (file) {
                    alert('Please upload an Excel .xlsx file.');
                }
            }
        });
    });
</script>
@endsection