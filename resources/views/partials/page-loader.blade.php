<div id="page-loader" class="page-loader page-loader--hidden"
    @isset($pollStatus) @if($pollStatus) data-status-url="{{ route('process.status.current') }}" data-status-stream-url="{{ route('process.status.stream') }}" @else data-static-complete="1" @endif @endisset
    @isset($autoRedirect) @if($autoRedirect) data-ready-redirect="{{ route('process.assignments.index') }}" @endif @endisset
    aria-live="polite" aria-busy="true">
    @include('partials.task-loader-card', [
        'title' => $title ?? 'Loading',
        'message' => $message ?? 'Please wait while we load your request.',
        'messageId' => 'page-loader-message',
        'messageDataLoader' => true,
        'useShell' => false,
        'cardClass' => 'task-loader-card page-loader-card',
    ])
</div>