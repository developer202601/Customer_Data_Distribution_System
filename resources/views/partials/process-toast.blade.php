@php
    $message = $message ?? session('status');
    $title = $title ?? 'Status update';
    $timeout = $timeout ?? 4500;
@endphp

@if(! empty($message))
<div class="process-toast" data-process-toast data-timeout="{{ (int) $timeout }}" role="status" aria-live="polite">
    <div class="process-toast__icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="11" stroke="#2b8a3e" stroke-width="2" fill="#e9f9ef" />
            <path d="M7 12.5L10.5 16L17 8" stroke="#2b8a3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </div>
    <div class="process-toast__body">
        <strong>{{ $title }}</strong>
        <span>{{ $message }}</span>
    </div>
    <button type="button" class="process-toast__close" data-toast-close aria-label="Dismiss notification">&times;</button>
</div>

@push('scripts')
    @once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-process-toast]').forEach(function (toast) {
                if (toast.dataset.toastReady === '1') {
                    return;
                }
                toast.dataset.toastReady = '1';
                var hideToast = function () {
                    toast.classList.add('is-hiding');
                    setTimeout(function () { toast.remove(); }, 400);
                };
                var timeout = parseInt(toast.getAttribute('data-timeout'), 10);
                if (Number.isNaN(timeout) || timeout <= 0) {
                    timeout = 4500;
                }
                var timer = setTimeout(hideToast, timeout);
                var closeBtn = toast.querySelector('[data-toast-close]');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function () {
                        clearTimeout(timer);
                        hideToast();
                    });
                }
                toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
                toast.addEventListener('mouseleave', function () {
                    timer = setTimeout(hideToast, 1500);
                });
            });
        });
    </script>
    @endonce
@endpush
@endif
