<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Call Center')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    @php
        $ccUsernameMissing = false;
        $sessionUser = session('user');
        if ($sessionUser && (isset($sessionUser['system']) && $sessionUser['system'] === 'cc')) {
            $uid = $sessionUser['id'] ?? null;
            if ($uid) {
                try {
                    $dbUser = \App\Models\CallCenter\CallCenterUser::find($uid);
                    if ($dbUser) {
                        $ccUsernameMissing = empty(trim((string)$dbUser->name));
                    } else {
                        $ccUsernameMissing = empty($sessionUser['name']);
                    }
                } catch (\Throwable $e) {
                    $ccUsernameMissing = empty($sessionUser['name']);
                }
            } else {
                $ccUsernameMissing = empty($sessionUser['name']);
            }
        }
    @endphp
    @if($ccUsernameMissing)
    <!-- Modal forcing callers to set display name on first login -->
    <div class="modal fade" id="ccSetNameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Welcome — please set your display name</h5>
                </div>
                <div class="modal-body">
                    <p class="text-muted">This name will be shown to customers and can be changed later in settings.</p>
                    <div class="mb-3">
                        <label for="ccDisplayName" class="form-label">Display name</label>
                        <input id="ccDisplayName" type="text" class="form-control" maxlength="255" />
                        <div id="ccDisplayNameError" class="form-text text-danger" style="display:none"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('logout') }}" method="post" class="d-inline me-auto">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Logout</button>
                    </form>
                    <button type="button" id="ccSaveDisplayName" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('ccSetNameModal');
                var input = document.getElementById('ccDisplayName');
                var saveBtn = document.getElementById('ccSaveDisplayName');
                var err = document.getElementById('ccDisplayNameError');
                // show modal (static backdrop) and prevent closing except via Save or Logout
                var bs = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
                bs.show();

                saveBtn.addEventListener('click', function () {
                    var name = input.value.trim();
                    if (!name) { err.style.display = 'block'; err.textContent = 'Please enter a name'; return; }
                    fetch("{{ route('cc.profile.setName') }}", {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ name: name })
                    }).then(function (r) { return r.json(); }).then(function (json) {
                        if (json && json.success) {
                            // reload page so server-side renders with name and navbar restored
                            window.location.reload();
                        } else {
                            err.style.display = 'block';
                            err.textContent = json.error || 'Failed to save name';
                        }
                    }).catch(function () {
                        err.style.display = 'block';
                        err.textContent = 'Failed to save name';
                    });
                });
            });
        })();
    </script>
    @endif

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
