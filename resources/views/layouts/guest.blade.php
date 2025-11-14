<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AdminLTE 4')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="hold-transition layout-top-nav">
    <div class="wrapper">
        @include('partials.navbar')
        <div class="content-wrapper">
            @yield('content')
        </div>
    </div>
</body>

</html>