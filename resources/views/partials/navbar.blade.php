<nav class="main-header navbar navbar-expand navbar-white navbar-light shadow-sm">
    <div class="container-fluid d-flex align-items-center justify-content-between" style="padding: 0px 20px;">
        <a href="/" class="navbar-brand d-flex align-items-center mb-0" style= "padding: 12px 0px;">
            <img src="{{ asset('images/slt-logo.svg') }}" alt="SLT Logo" style="height:48px; margin-right: 1rem;">
        </a>
        @php($navbarRight = trim($__env->yieldContent('navbar-right')))
        @if($navbarRight !== '')
        <div class="ml-auto d-flex align-items-center" style="gap: 1rem;">
            {!! $navbarRight !!}
        </div>
        @endif
    </div>
</nav>