<nav class="main-header navbar navbar-expand navbar-white navbar-light shadow-sm">
    <div class="container-fluid d-flex align-items-center">
        <a href="/" class="navbar-brand d-flex align-items-center mb-0">
            <img src="{{ asset('images/slt-logo.svg') }}" alt="SLT Logo" style="height:48px;" class="mr-3">
        </a>
        @php($navbarRight = trim($__env->yieldContent('navbar-right')))
        @if($navbarRight !== '')
        <div class="ml-auto d-flex align-items-center" style="column-gap: 15px;">
            {!! $navbarRight !!}
        </div>
        @endif
    </div>
</nav>