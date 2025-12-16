<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Call Center')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="hold-transition sidebar-mini cc-layout">
    @if(View::hasSection('loaderAutoRedirect'))
        @include('partials.page-loader', ['autoRedirect' => true, 'pollStatus' => true])
    @elseif(View::hasSection('loaderPollStatus'))
        @include('partials.page-loader', ['pollStatus' => true])
    @else
        @include('partials.page-loader', ['pollStatus' => false])
    @endif

    <div class="wrapper">

        <!-- Use master navbar so header/footer match the main site -->
        @include('partials.navbar')
        <!-- /.navbar -->

        <!-- Top toast for flash messages (kept for CC-specific toasts) -->
        @include('partials.top-toast')

        <!-- Offcanvas left sliding panel for Call Center (renders below navbar) -->
        @include('partials.cc-sidebar')

        <!-- small fixed toggle button so users can open the call center sidebar -->
        <button class="btn btn-outline-secondary cc-sidebar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#ccSidebar" aria-controls="ccSidebar" aria-label="Open menu">
            ☰
        </button>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            @yield('content')
        </div>
        <!-- /.content-wrapper -->
    </div>

    <!-- Footer placed outside the main wrapper so it's not affected by content padding -->
    <footer class="main-footer text-center py-3">
        All right reserved
    </footer>

    <script>
        // If Bootstrap's JS isn't present (e.g., dev assets not running), load from CDN.
        (function () {
            function loadScript(src, cb) {
                var s = document.createElement('script'); s.src = src; s.async = true; s.onload = cb; document.head.appendChild(s);
            }
            if (typeof window.bootstrap === 'undefined') {
                loadScript('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', function () {
                    console.log('Bootstrap fallback loaded from CDN');
                });
            } else {
                console.log('Bootstrap already available');
            }
        })();
    </script>

    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                    var off = document.getElementById('ccSidebar');
                    var toggle = document.querySelector('.cc-sidebar-toggle');
                if (!off || !toggle) return;

                    function cleanupBackdrop() {
                        // remove any leftover offcanvas backdrop elements and related body classes
                        try {
                            document.querySelectorAll('.offcanvas-backdrop').forEach(function (el) { el.parentNode && el.parentNode.removeChild(el); });
                            document.body.classList.remove('offcanvas-backdrop');
                            document.body.classList.remove('modal-open');
                        } catch (e) { }
                    }

                    var hideToggle = function () {
                        toggle.classList.add('cc-toggle-hidden');
                    };

                    var showToggle = function () {
                        toggle.classList.remove('cc-toggle-hidden');
                    };

                var markBodyOpen = function () {
                    document.body.classList.add('cc-sidebar-open');
                };

                var clearBodyOpen = function () {
                    document.body.classList.remove('cc-sidebar-open');
                };

                toggle.addEventListener('click', function () {
                    hideToggle();
                });

                if (typeof bootstrap !== 'undefined') {
                    off.addEventListener('show.bs.offcanvas', function () {
                        markBodyOpen();
                        hideToggle();
                    });
                    // ensure clicking the backdrop or anywhere outside the offcanvas closes it
                    document.addEventListener('click', function (ev) {
                        try {
                            if (!off.classList.contains('show')) return;
                            // ignore clicks inside sidebar or on the toggle
                            if (ev.target.closest && (ev.target.closest('#ccSidebar') || ev.target.closest('.cc-sidebar-toggle'))) return;
                            var inst = bootstrap.Offcanvas.getInstance(off) || new bootstrap.Offcanvas(off);
                            inst.hide();
                        } catch (err) { }
                    }, true);
                    off.addEventListener('hide.bs.offcanvas', function () {
                        clearBodyOpen();
                        showToggle();
                    });
                    off.addEventListener('hidden.bs.offcanvas', function () {
                        // ensure any lingering backdrop is removed when fully hidden
                        cleanupBackdrop();
                        clearBodyOpen();
                        showToggle();
                    });
                } else {
                    document.addEventListener('click', function (ev) {
                        if (ev.target.closest && ev.target.closest('#ccSidebar')) return;
                        clearBodyOpen();
                        showToggle();
                    });
                }
            });
        })();
    </script>

    @stack('scripts')
</body>

</html>
