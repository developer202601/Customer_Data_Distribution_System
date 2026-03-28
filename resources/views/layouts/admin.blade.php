<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="csp-nonce" content="{{ $cspNonce }}">
    <title>{{ session('user.system') === 'master' ? 'CDDS' : 'PRMS' }} | @yield('title', 'Dashboard')</title>
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

<body class="hold-transition sidebar-mini">
    @include('partials.page-loader', ['pollStatus' => false])
    <div class="wrapper">

        <!-- Navbar -->
        @include('partials.navbar')
        <!-- /.navbar -->

        <!-- Main Sidebar Container removed for custom navbar layout -->

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper" style="padding: 29px;">
            @yield('content')
        </div>
        <!-- /.content-wrapper -->

        <!-- Main Footer
        <footer class="main-footer">
            <strong>Copyright &copy; 2014-2025 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
        </footer> -->
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
    </div>
    <!-- ./wrapper -->

    @stack('scripts')
</body>

</html>