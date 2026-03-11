@php
    $ccRouteName = request()->route()?->getName() ?? '';
@endphp
<div class="offcanvas offcanvas-start cc-offcanvas" tabindex="-1" id="ccSidebar" aria-labelledby="ccSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title mb-0" id="ccSidebarLabel">Call Center</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column cc-sidebar-nav">
            @php $userAssignment = session('user.assignment') ?? null; @endphp
            @if(session('user.is_admin'))
                @if(session('user.assignment') === 'super')
                    <a class="nav-link{{ $ccRouteName === 'cc.dashboard' ? ' active' : '' }}" href="{{ route('cc.dashboard') }}" aria-current="{{ $ccRouteName === 'cc.dashboard' ? 'page' : '' }}">Overview</a>
                    <a class="nav-link{{ $ccRouteName === 'cc.super.regions' ? ' active' : '' }}" href="{{ route('cc.super.regions') }}" aria-current="{{ $ccRouteName === 'cc.super.regions' ? 'page' : '' }}">Region Management</a>
                    <a class="nav-link{{ $ccRouteName === 'cc.users.index' ? ' active' : '' }}" href="{{ route('cc.users.index') }}" aria-current="{{ $ccRouteName === 'cc.users.index' ? 'page' : '' }}">User Management</a>
                @elseif(session('user.assignment') && session('user.assignment') !== 'super')
                    @if(\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'rtom_'))
                        <a class="nav-link{{ $ccRouteName === 'cc.rtom.dashboard' ? ' active' : '' }}" href="{{ route('cc.rtom.dashboard') }}" aria-current="{{ $ccRouteName === 'cc.rtom.dashboard' ? 'page' : '' }}">RTO Dashboard</a>
                        <a class="nav-link{{ $ccRouteName === 'cc.region.assign.index' ? ' active' : '' }}" href="{{ route('cc.region.assign.index') }}" aria-current="{{ $ccRouteName === 'cc.region.assign.index' ? 'page' : '' }}">Assign Supervisors</a>
                    @elseif(\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_'))
                        <a class="nav-link{{ $ccRouteName === 'cc.supervisor.dashboard' ? ' active' : '' }}" href="{{ route('cc.supervisor.dashboard') }}" aria-current="{{ $ccRouteName === 'cc.supervisor.dashboard' ? 'page' : '' }}">Supervisor Dashboard</a>
                        <a class="nav-link{{ $ccRouteName === 'cc.users.index' ? ' active' : '' }}" href="{{ route('cc.users.index') }}" aria-current="{{ $ccRouteName === 'cc.users.index' ? 'page' : '' }}">Manage Callers</a>
                    @else
                        <a class="nav-link{{ $ccRouteName === 'cc.region.dashboard' ? ' active' : '' }}" href="{{ route('cc.region.dashboard') }}" aria-current="{{ $ccRouteName === 'cc.region.dashboard' ? 'page' : '' }}">Region Dashboard</a>
                        <a class="nav-link{{ $ccRouteName === 'cc.region.review' ? ' active' : '' }}" href="{{ route('cc.region.review') }}" aria-current="{{ $ccRouteName === 'cc.region.review' ? 'page' : '' }}">Review Report Rows</a>
                        <a class="nav-link{{ $ccRouteName === 'cc.region.index' ? ' active' : '' }}" href="{{ route('cc.region.index') }}" aria-current="{{ $ccRouteName === 'cc.region.index' ? 'page' : '' }}">RTO Admins</a>
                    @endif
                @endif
                @php $isRegion = session('user.assignment') && str_starts_with(session('user.assignment'), 'REGION'); @endphp
                @if(! $isRegion && ! \Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'rtom_'))
                    @php $reportsRoute = (session('user.assignment') === 'super') ? 'cc.reports.history' : 'cc.reports'; @endphp
                    <a class="nav-link{{ str_starts_with($ccRouteName, 'cc.reports') ? ' active' : '' }}" href="{{ route($reportsRoute) }}" aria-current="{{ str_starts_with($ccRouteName, 'cc.reports') ? 'page' : '' }}">Reports</a>
                @endif
            @endif
            @if(session('user.assignment') !== 'super' && ! (session('user.assignment') && str_starts_with(session('user.assignment'), 'REGION')) && ! \Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'rtom_') && ! \Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_') )
                <a class="nav-link{{ str_starts_with($ccRouteName, 'cc.assignments') ? ' active' : '' }}" href="{{ route('cc.assignments.manage') }}" aria-current="{{ str_starts_with($ccRouteName, 'cc.assignments') ? 'page' : '' }}">Assigned Rows</a>
            @endif
            <!-- <a class="nav-link" href="#">Queues</a> -->
        </nav>
    </div>
</div>
