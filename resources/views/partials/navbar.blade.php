<nav class="main-header navbar navbar-expand navbar-white navbar-light shadow-sm">
    <div class="container-fluid d-flex align-items-center" style="padding: 0px 20px;">
        <a href="{{ (session('user.system') == 'cc' || request()->is('cc/*')) ? route('cc.dashboard') : url('/') }}" class="navbar-brand d-flex align-items-center mb-0" style="padding: 12px 0px;">
            <img src="{{ asset('images/slt-logo.svg') }}" alt="SLT Logo" style="height:48px; margin-right: 1rem;">
        </a>
        @php
            $navbarRight = trim($__env->yieldContent('navbar-right'));
            $navLinks = [];
        @endphp
        <ul class="navbar-nav flex-row align-items-center gap-2 ms-3">
            @foreach($navLinks as $link)
            @php
                $isActive = request()->routeIs(...$link['active']);
            @endphp
            <li class="nav-item">
                <a href="{{ route($link['route']) }}" class="nav-link {{ $isActive ? 'active fw-semibold' : '' }}" aria-current="{{ $isActive ? 'page' : 'false' }}">
                    {{ $link['label'] }}
                </a>
            </li>
            @endforeach
        </ul>
        <div class="ms-auto d-flex align-items-center" style="gap: 1rem;">
            <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Toggle theme" data-theme="light">
                <span class="theme-toggle__icon theme-toggle__icon--sun">☀</span>
                <span class="theme-toggle__icon theme-toggle__icon--moon">☾</span>
                <span class="visually-hidden">Toggle theme</span>
            </button>
            @php
                $sessionUser = session('user');
                $showMinimalLogout = false;
                if ($sessionUser && (isset($sessionUser['system']) && $sessionUser['system'] === 'cc')) {
                    $uid = $sessionUser['id'] ?? null;
                    if ($uid) {
                        try {
                            $dbUser = \App\Models\CallCenter\CallCenterUser::find($uid);
                            if ($dbUser) {
                                $showMinimalLogout = empty(trim((string)$dbUser->name));
                            } else {
                                $showMinimalLogout = empty($sessionUser['name']);
                            }
                        } catch (\Throwable $e) {
                            $showMinimalLogout = empty($sessionUser['name']);
                        }
                    } else {
                        $showMinimalLogout = empty($sessionUser['name']);
                    }
                }
            @endphp
            @if($showMinimalLogout)
                <div class="d-flex align-items-center" style="gap: 1rem;">
                    <form action="{{ route('logout') }}" method="post" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Logout</button>
                    </form>
                </div>
            @else
                @if($navbarRight !== '')
                <div class="d-flex align-items-center" style="gap: 1rem;">
                    {!! $navbarRight !!}
                </div>
                @endif
            @endif
        </div>
    </div>
</nav>