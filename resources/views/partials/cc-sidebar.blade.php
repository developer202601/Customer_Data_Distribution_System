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
            <a class="nav-link{{ $ccRouteName === 'cc.dashboard' ? ' active' : '' }}" href="{{ route('cc.dashboard') }}" aria-current="{{ $ccRouteName === 'cc.dashboard' ? 'page' : '' }}">Overview</a>
            @if(session('user.is_admin'))
                @if(session('user.assignment') === 'super')
                    <a class="nav-link{{ $ccRouteName === 'cc.users.assign.index' ? ' active' : '' }}" href="{{ route('cc.users.assign.index') }}" aria-current="{{ $ccRouteName === 'cc.users.assign.index' ? 'page' : '' }}">Assign Users</a>
                @elseif(session('user.assignment') && session('user.assignment') !== 'super')
                    <a class="nav-link{{ $ccRouteName === 'cc.region.dashboard' ? ' active' : '' }}" href="{{ route('cc.region.dashboard') }}" aria-current="{{ $ccRouteName === 'cc.region.dashboard' ? 'page' : '' }}">Region Dashboard</a>
                    <a class="nav-link{{ $ccRouteName === 'cc.region.assign.index' ? ' active' : '' }}" href="{{ route('cc.region.assign.index') }}" aria-current="{{ $ccRouteName === 'cc.region.assign.index' ? 'page' : '' }}">Assign Supervisors</a>
                @endif
                <a class="nav-link{{ str_starts_with($ccRouteName, 'cc.reports') ? ' active' : '' }}" href="{{ route('cc.reports') }}" aria-current="{{ str_starts_with($ccRouteName, 'cc.reports') ? 'page' : '' }}">Reports</a>
            @endif
            @if(session('user.assignment') !== 'super')
                <a class="nav-link{{ str_starts_with($ccRouteName, 'cc.assignments') ? ' active' : '' }}" href="{{ route('cc.assignments.manage') }}" aria-current="{{ str_starts_with($ccRouteName, 'cc.assignments') ? 'page' : '' }}">Assigned Rows</a>
            @endif
            <!-- <a class="nav-link" href="#">Queues</a> -->
        </nav>
    </div>
</div>
