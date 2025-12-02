<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AdminLTE 4 | Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="hold-transition sidebar-mini">
    @if(View::hasSection('loaderAutoRedirect'))
        @include('partials.page-loader', ['autoRedirect' => true, 'pollStatus' => true])
    @elseif(View::hasSection('loaderPollStatus'))
        @include('partials.page-loader', ['pollStatus' => true])
    @else
        @include('partials.page-loader', ['pollStatus' => false])
    @endif
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
            All right reserved
        </footer>
    </div>
    <!-- ./wrapper -->

    @stack('scripts')
</body>

</html>