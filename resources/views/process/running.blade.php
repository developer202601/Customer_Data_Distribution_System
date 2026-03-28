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
<div class="task-loader-meta text-muted text-center" id="process-running-elapsed" aria-live="polite"></div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function() {
        var statusUrl = @json(route('process.status.current'));
        var assignmentsUrl = @json(route('process.assignments.index'));
        var messageEl = document.getElementById('task-loader-message');
        var redirecting = false;
        var elapsedEl = document.getElementById('process-running-elapsed');
        var elapsedStart = Date.now();
        var elapsedTimer = null;

        if (elapsedEl) {
            elapsedTimer = window.setInterval(function() {
                var elapsed = Math.max(0, Math.floor((Date.now() - elapsedStart) / 1000));
                var mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
                var secs = String(elapsed % 60).padStart(2, '0');
                elapsedEl.textContent = 'Elapsed ' + mins + ':' + secs;
            }, 1000);
        }

        var tick = function() {
            if (redirecting) {
                return;
            }

            fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    cache: 'no-store',
                    credentials: 'same-origin',
                })
                .then(function(response) {
                    if (!response.ok) {
                        return null;
                    }

                    return response.json();
                })
                .then(function(payload) {
                    if (!payload) {
                        return;
                    }

                    if (messageEl) {
                        var msg = payload.message || 'Processing...';
                        messageEl.textContent = msg;
                    }

                    if (payload.redirect_url) {
                        redirecting = true;
                        if (elapsedTimer) {
                            window.clearInterval(elapsedTimer);
                        }
                        window.location.href = payload.redirect_url;
                        return;
                    }

                    if (payload.status === 'ready' || payload.status === 'exports_pending') {
                        redirecting = true;
                        if (elapsedTimer) {
                            window.clearInterval(elapsedTimer);
                        }
                        window.location.href = assignmentsUrl;
                        return;
                    }

                    if (payload.status === 'failed') {
                        redirecting = true;
                        if (elapsedTimer) {
                            window.clearInterval(elapsedTimer);
                        }
                        window.location.href = @json(route('master.upload.create'));
                    }
                })
                .catch(function() {});
        };

        tick();
        window.setInterval(tick, 1000);
    });
</script>
@endpush