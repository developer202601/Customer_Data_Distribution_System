<div id="page-loader" class="page-loader page-loader--hidden"
    @isset($pollStatus) @if($pollStatus) data-status-url="{{ route('process.status.current') }}" data-status-stream-url="{{ route('process.status.stream') }}" @else data-static-complete="1" @endif @endisset
    @isset($autoRedirect) @if($autoRedirect) data-ready-redirect="{{ route('process.assignments.index') }}" @endif @endisset
    aria-live="polite" aria-busy="true">
    <div class="page-loader__inner">
        <div class="page-loader__spinner" role="presentation"></div>
        <div class="page-loader__content">
            <div class="page-loader__message" data-loader-message>Loading…</div>
            <div class="page-loader__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="page-loader__progress-bar" data-loader-bar></div>
            </div>
            <div class="page-loader__line-track" aria-hidden="true">
                <div class="page-loader__line-fill" data-loader-line-fill></div>
            </div>
            <div class="page-loader__percentage" data-loader-percent>0%</div>
            <div class="page-loader__heartbeat" data-loader-heartbeat>Active</div>
        </div>
    </div>
</div>