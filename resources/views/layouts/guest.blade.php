<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CDDS')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <script nonce="{{ $cspNonce ?? '' }}">(function(){try{if(sessionStorage.getItem('cdds-loader-shown')!=='1'){document.documentElement.setAttribute('data-loader-init','1');}}catch(e){}})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="hold-transition layout-top-nav guest-layout">
    @include('partials.page-loader', ['pollStatus' => false])
    <div class="wrapper">
        @include('partials.navbar')
        <div class="content-wrapper guest-content">
            @yield('content')
        </div>
    </div>
</body>

</html>