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
@include('partials.task-loader-card', [
    'title' => 'Processing dataset',
    'message' => 'Please wait while the system applies the confirmed configuration and generates the filtered outputs.',
])
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function () {
        var statusUrl = @json(route('process.status.current'));
        var assignmentsUrl = @json(route('process.assignments.index'));
        var messageEl = document.getElementById('task-loader-message');
        var redirecting = false;

        var tick = function () {
            if (redirecting) {
                return;
            }

            fetch(statusUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            })
            .then(function (response) {
                if (!response.ok) {
                    return null;
                }

                return response.json();
            })
            .then(function (payload) {
                if (!payload) {
                    return;
                }

                if (messageEl) {
                    var msg = payload.message || 'Processing...';
                    messageEl.textContent = msg;
                }

                if (payload.redirect_url) {
                    redirecting = true;
                    window.location.href = payload.redirect_url;
                    return;
                }

                if (payload.status === 'ready') {
                    redirecting = true;
                    window.location.href = assignmentsUrl;
                    return;
                }

                if (payload.status === 'failed') {
                    redirecting = true;
                    window.location.href = @json(route('master.upload.create'));
                }
            })
            .catch(function () {});
        };

        tick();
        window.setInterval(tick, 1000);
    });
</script>
@endpush
