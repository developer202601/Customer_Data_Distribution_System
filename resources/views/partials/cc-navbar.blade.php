<nav class="main-header navbar navbar-expand navbar-white navbar-light shadow-sm">
    <div class="container-fluid d-flex align-items-center" style="padding: 12px 20px;">
        <a href="/" class="navbar-brand d-flex align-items-center mb-0" style="padding: 12px 0px;">
            <img src="{{ asset('images/slt-logo.svg') }}" alt="SLT Logo" style="height:48px; margin-right: 1rem;">
        </a>

        <div class="ms-auto d-flex align-items-center" style="gap: 1rem;">
            <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Toggle theme" data-theme="light">
                <span class="theme-toggle__icon theme-toggle__icon--sun">☀</span>
                <span class="theme-toggle__icon theme-toggle__icon--moon">☾</span>
                <span class="visually-hidden">Toggle theme</span>
            </button>

            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">Logout</button>
            </form>
        </div>
    </div>
</nav>
