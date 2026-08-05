<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="csp-nonce" content="{{ $cspNonce }}">
    <title>{{ session('user.system') === 'master' ? 'CDDS' : 'PRMS' }} | @yield('title', 'Call Center')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <script nonce="{{ $cspNonce ?? '' }}">
        (function() {
            try {
                if (sessionStorage.getItem('cdds-loader-shown') !== '1') {
                    document.documentElement.setAttribute('data-loader-init', '1');
                }
            } catch (e) {}
        })();
    </script>
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

        <!-- Offcanvas left sliding panel for the current system -->
        @if(session('user.system') === 'rb')
            @include('partials.rb-sidebar')
        @else
            @include('partials.cc-sidebar')
        @endif

        <!-- small fixed toggle button so users can open the sidebar -->
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
        <div style="display:inline-flex;align-items:center;gap:.5rem;white-space:nowrap;">
            <span>All rights reserved</span>
            <span>|</span>
            <span>Powered by</span>
            <a href="" style="display:inline-flex;align-items:center;gap:.25rem;">
                <img src="{{ asset('images/Transzent-logo.png') }}" alt="Transzent" style="height:24px;max-height:24px;padding-bottom:1px;" />
            </a>
        </div>
    </footer>


    <script nonce="{{ $cspNonce ?? '' }}">
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                var off = document.getElementById('ccSidebar');
                var toggle = document.querySelector('.cc-sidebar-toggle');
                if (!off || !toggle) return;

                function cleanupBackdrop() {
                    // remove any leftover offcanvas backdrop elements and related body classes
                    try {
                        document.querySelectorAll('.offcanvas-backdrop').forEach(function(el) {
                            el.parentNode && el.parentNode.removeChild(el);
                        });
                        document.querySelectorAll('.modal-backdrop').forEach(function(el) {
                            el.parentNode && el.parentNode.removeChild(el);
                        });
                        document.body.classList.remove('offcanvas-backdrop');
                        document.body.classList.remove('modal-open');
                        // Bootstrap may also apply inline scroll locks.
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    } catch (e) {}
                }

                // When returning via browser back/forward cache, DOMContentLoaded won't re-run.
                // Ensure any stale scroll locks/backdrops are removed so the page scrolls.
                window.addEventListener('pageshow', function() {
                    cleanupBackdrop();
                    clearBodyOpen();
                    showToggle();
                });

                var hideToggle = function() {
                    toggle.classList.add('cc-toggle-hidden');
                };

                var showToggle = function() {
                    toggle.classList.remove('cc-toggle-hidden');
                };

                var markBodyOpen = function() {
                    document.body.classList.add('cc-sidebar-open');
                };

                var clearBodyOpen = function() {
                    document.body.classList.remove('cc-sidebar-open');
                };

                toggle.addEventListener('click', function() {
                    hideToggle();
                });

                if (typeof bootstrap !== 'undefined') {
                    off.addEventListener('show.bs.offcanvas', function() {
                        markBodyOpen();
                        hideToggle();
                    });
                    // ensure clicking the backdrop or anywhere outside the offcanvas closes it
                    document.addEventListener('click', function(ev) {
                        try {
                            if (!off.classList.contains('show')) return;
                            var target = ev.target;
                            if (ev.target.closest && (ev.target.closest('#ccSidebar') || ev.target.closest('.cc-sidebar-toggle'))) return;
                            var inst = bootstrap.Offcanvas.getInstance(off) || new bootstrap.Offcanvas(off);
                            inst.hide();
                            cleanupBackdrop();
                        } catch (err) {}
                    }, true);
                    off.addEventListener('hide.bs.offcanvas', function() {
                        clearBodyOpen();
                        showToggle();
                    });
                    off.addEventListener('hidden.bs.offcanvas', function() {
                        // ensure any lingering backdrop is removed when fully hidden
                        cleanupBackdrop();
                        clearBodyOpen();
                        showToggle();
                    });
                } else {
                    document.addEventListener('click', function(ev) {
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