@php
    $loaderTitle = $title ?? 'Processing dataset';
    $loaderMessage = $message ?? 'Please wait while the system processes your request.';
    $loaderButtonHref = $buttonHref ?? null;
    $loaderButtonLabel = $buttonLabel ?? 'Continue';
    $loaderButtonId = $buttonId ?? 'task-loader-action';
    $loaderButtonDisabled = (bool) ($buttonDisabled ?? false);
    $loaderShellClass = $shellClass ?? 'task-loader-shell';
    $loaderCardClass = $cardClass ?? 'task-loader-card';
    $loaderSpinnerClass = $spinnerClass ?? 'task-loader-spinner';
    $loaderMessageId = $messageId ?? 'task-loader-message';
    $loaderUseShell = (bool) ($useShell ?? true);
    $loaderMessageData = (bool) ($messageDataLoader ?? false);
@endphp

@if($loaderUseShell)
<div class="{{ $loaderShellClass }}">
@endif
    <div class="{{ $loaderCardClass }}">
        <div class="{{ $loaderSpinnerClass }}" aria-hidden="true"></div>
        <h1 class="task-loader-title">{{ $loaderTitle }}</h1>
        <p class="task-loader-text"
            @if($loaderMessageId) id="{{ $loaderMessageId }}" @endif
            @if($loaderMessageData) data-loader-message @endif
        >{{ $loaderMessage }}</p>
        @if($loaderButtonHref)
            <div class="task-loader-actions">
                <a
                    id="{{ $loaderButtonId }}"
                    href="{{ $loaderButtonHref }}"
                    class="btn btn-primary{{ $loaderButtonDisabled ? ' disabled' : '' }}"
                    @if($loaderButtonDisabled) aria-disabled="true" @endif
                >{{ $loaderButtonLabel }}</a>
            </div>
        @endif
    </div>
@if($loaderUseShell)
</div>
@endif