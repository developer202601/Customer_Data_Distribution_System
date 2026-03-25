@php
    $loaderTitle = $title ?? 'Processing dataset';
    $loaderMessage = $message ?? 'Please wait while the system processes your request.';
    $loaderButtonHref = $buttonHref ?? null;
    $loaderButtonLabel = $buttonLabel ?? 'Continue';
    $loaderButtonId = $buttonId ?? 'task-loader-action';
    $loaderButtonDisabled = (bool) ($buttonDisabled ?? false);
@endphp

<div class="task-loader-shell">
    <div class="task-loader-card">
        <div class="task-loader-spinner" aria-hidden="true"></div>
        <h1 class="task-loader-title">{{ $loaderTitle }}</h1>
        <p class="task-loader-text" id="task-loader-message">{{ $loaderMessage }}</p>
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
</div>