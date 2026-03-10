@extends('layouts.admin')

@section('title', 'Processing')

@section('loaderAutoRedirect', true)

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <h1 class="process-upload-title mb-2">Processing dataset</h1>
                <p class="text-muted mb-0">Please wait while the system applies the confirmed configuration and generates the filtered outputs.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function () {
        try {
            if (window.CDDSLoader && typeof window.CDDSLoader.show === 'function') {
                window.CDDSLoader.show();
                return;
            }

            var loader = document.getElementById('page-loader');
            if (loader) loader.classList.remove('page-loader--hidden');
        } catch (e) {}
    });
</script>
@endpush
