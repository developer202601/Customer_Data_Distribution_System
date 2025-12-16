@if(session('success') || session('status'))
    <div aria-live="polite" aria-atomic="true" style="z-index:2100; position:fixed; left:50%; transform:translateX(-50%); top:0.75rem;">
        <div id="topToast" class="toast align-items-center text-bg-light border shadow-sm" role="alert" aria-live="assertive" aria-atomic="true" style="min-width:360px; max-width:720px;">
            <div class="d-flex">
                <div class="toast-body text-center w-100">
                    {{ session('success') ?? session('status') }}
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            try {
                var toastEl = document.getElementById('topToast');
                if (toastEl) {
                    var t = new bootstrap.Toast(toastEl, { delay: 4000 });
                    t.show();
                }
            } catch (e) {
                // Bootstrap not loaded yet; try again shortly
                setTimeout(function () {
                    try { if (window.bootstrap && document.getElementById('topToast')) new bootstrap.Toast(document.getElementById('topToast'), { delay: 4000 }).show(); } catch (e) {}
                }, 600);
            }
        })();
    </script>
@endif
