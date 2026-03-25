@extends('layouts.admin')

@section('title', 'Waiting for Confirmation')

@section('loaderPollStatus', true)

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
@include('partials.task-loader-card', [
    'title' => 'Preparing confirmation',
    'message' => 'We are processing the uploaded master and exclusion files. You will be redirected to configuration confirmation automatically.',
    'buttonHref' => route('process.confirm.create'),
    'buttonLabel' => 'Open configuration now',
    'buttonId' => 'confirm-wait-open-now',
    'buttonDisabled' => true,
])

<div id="validation-progress-template" style="display: none;">
    <div class="mt-4 w-100" id="tpl-validation-progress-container">
        <div class="progress" style="height: 8px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
        </div>
        <div class="d-flex justify-content-between mt-2 text-muted small">
            <span class="progress-count"></span>
            <span class="progress-eta">Estimating time...</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function () {
        var statusUrl = @json(route('process.status.current'));
        var confirmUrl = @json(route('process.confirm.create'));
        var redirecting = false;
        var messageEl = document.getElementById('task-loader-message');
        var openNowEl = document.getElementById('confirm-wait-open-now');

        // Progress elements
        var progressContainer = null;
        var progressBar = null;
        var progressCount = null;
        var progressEta = null;
        var progressInjected = false;

        var injectProgress = function() {
            if (progressInjected) return;
            
            var card = document.querySelector('.task-loader-card');
            var template = document.getElementById('tpl-validation-progress-container');
            
            if (card && template) {
                // Insert before the actions div if it exists, otherwise append
                var actions = card.querySelector('.task-loader-actions');
                
                // Clone the node to avoid moving the hidden template wrapper
                var clone = template.cloneNode(true);
                clone.id = 'validation-progress-active'; // New ID
                
                if (actions) {
                    card.insertBefore(clone, actions);
                } else {
                    card.appendChild(clone);
                }
                
                progressContainer = clone;
                progressBar = clone.querySelector('.progress-bar');
                progressCount = clone.querySelector('.progress-count');
                progressEta = clone.querySelector('.progress-eta');
                progressInjected = true;
            }
        };

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

                // Handle Progress Bar
                if (payload.processed_rows && payload.total_rows > 0) {
                    injectProgress();
                    
                    if (progressContainer) {
                        var processed = payload.processed_rows;
                        var total = payload.total_rows;
                        var pct = Math.min(100, Math.round((processed / total) * 100));
                        
                        progressBar.style.width = pct + '%';
                        progressCount.textContent = processed.toLocaleString() + ' / ' + total.toLocaleString() + ' rows';
                        
                        if (payload.started_at) {
                            var startTs = payload.started_at; // unix timestamp seconds
                            var nowTs = Math.floor(Date.now() / 1000);
                            var elapsed = nowTs - startTs;
                            
                            if (elapsed > 2 && processed > 0) {
                                var rate = processed / elapsed; // rows per sec
                                var remaining = total - processed;
                                var secLeft = remaining / rate;
                                
                                if (secLeft < 60) {
                                    progressEta.textContent = Math.ceil(secLeft) + 's remaining';
                                } else {
                                    var mins = Math.floor(secLeft / 60);
                                    var secs = Math.ceil(secLeft % 60);
                                    progressEta.textContent = mins + 'm ' + secs + 's remaining';
                                }
                            }
                        }
                    }
                } else if (payload.status === 'validating' && payload.progress === 6) {
                    // Show indeterminate progress if needed, or hide
                }

                if (payload.status === 'waiting_confirmation') {
                    if (openNowEl) {
                        openNowEl.classList.remove('disabled');
                        openNowEl.removeAttribute('aria-disabled');
                    }
                    redirecting = true;
                    window.location.replace(confirmUrl + '?refresh=' + Date.now());
                    return;
                }

                if (payload.redirect_url) {
                    var target = document.createElement('a');
                    target.href = payload.redirect_url;
                    if (target.pathname !== window.location.pathname || (target.search || '') !== (window.location.search || '')) {
                        window.location.href = payload.redirect_url;
                    }
                }
            })
            .catch(function () {});
        };

        tick();
        window.setInterval(tick, 1000);
    });
</script>
@endpush
