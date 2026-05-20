@php
    $rbRouteName = request()->route()?->getName() ?? '';
    $assignment = strtolower(trim((string) (session('user.assignment') ?? '')));
    $isAdmin = session('user.is_admin');
@endphp
<div class="offcanvas offcanvas-start cc-offcanvas" tabindex="-1" id="ccSidebar" aria-labelledby="ccSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title mb-0" id="ccSidebarLabel">Regional Billing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column cc-sidebar-nav">
            @if($isAdmin)
                @if($assignment === 'super')
                    <a class="nav-link{{ $rbRouteName === 'rb.dashboard' ? ' active' : '' }}" href="{{ route('rb.dashboard') }}" aria-current="{{ $rbRouteName === 'rb.dashboard' ? 'page' : '' }}">Overview</a>
                    <a class="nav-link{{ $rbRouteName === 'rb.regions.index' ? ' active' : '' }}" href="{{ route('rb.regions.index') }}" aria-current="{{ $rbRouteName === 'rb.regions.index' ? 'page' : '' }}">Regions</a>
                    <a class="nav-link{{ $rbRouteName === 'rb.users.index' ? ' active' : '' }}" href="{{ route('rb.users.index') }}" aria-current="{{ $rbRouteName === 'rb.users.index' ? 'page' : '' }}">User Management</a>
                    <a class="nav-link{{ str_starts_with($rbRouteName, 'rb.reports') ? ' active' : '' }}" href="{{ route('rb.reports.history') }}" aria-current="{{ str_starts_with($rbRouteName, 'rb.reports') ? 'page' : '' }}">Reports</a>
                @elseif(str_starts_with($assignment, 'rtom_'))
                    <a class="nav-link{{ $rbRouteName === 'rb.rtom.dashboard' ? ' active' : '' }}" href="{{ route('rb.rtom.dashboard') }}" aria-current="{{ $rbRouteName === 'rb.rtom.dashboard' ? 'page' : '' }}">RTO Dashboard</a>
                    <a class="nav-link{{ str_starts_with($rbRouteName, 'rb.users') ? ' active' : '' }}" href="{{ route('rb.users.index') }}" aria-current="{{ str_starts_with($rbRouteName, 'rb.users') ? 'page' : '' }}">Manage Callers</a>
                    <a class="nav-link{{ str_starts_with($rbRouteName, 'rb.reports') ? ' active' : '' }}" href="{{ route('rb.reports') }}" aria-current="{{ str_starts_with($rbRouteName, 'rb.reports') ? 'page' : '' }}">Reports</a>
                @elseif(str_starts_with($assignment, 'supervisor_'))
                    <a class="nav-link{{ $rbRouteName === 'rb.supervisor.dashboard' ? ' active' : '' }}" href="{{ route('rb.supervisor.dashboard') }}" aria-current="{{ $rbRouteName === 'rb.supervisor.dashboard' ? 'page' : '' }}">Supervisor Dashboard</a>
                    <a class="nav-link{{ str_starts_with($rbRouteName, 'rb.reports') ? ' active' : '' }}" href="{{ route('rb.reports') }}" aria-current="{{ str_starts_with($rbRouteName, 'rb.reports') ? 'page' : '' }}">Reports</a>
                @else
                    <a class="nav-link{{ $rbRouteName === 'rb.region.dashboard' ? ' active' : '' }}" href="{{ route('rb.region.dashboard') }}" aria-current="{{ $rbRouteName === 'rb.region.dashboard' ? 'page' : '' }}">Region Dashboard</a>
                    <a class="nav-link{{ str_starts_with($rbRouteName, 'rb.reports') ? ' active' : '' }}" href="{{ route('rb.reports') }}" aria-current="{{ str_starts_with($rbRouteName, 'rb.reports') ? 'page' : '' }}">Review Reports</a>
                    <a class="nav-link{{ $rbRouteName === 'rb.region.index' ? ' active' : '' }}" href="{{ route('rb.region.index') }}" aria-current="{{ $rbRouteName === 'rb.region.index' ? 'page' : '' }}">RTO Admins</a>
                @endif
            @else
                <a class="nav-link{{ str_starts_with($rbRouteName, 'rb.assignments') ? ' active' : '' }}" href="{{ route('rb.assignments.manage') }}" aria-current="{{ str_starts_with($rbRouteName, 'rb.assignments') ? 'page' : '' }}">Assignments</a>
            @endif
        </nav>
    </div>
</div>
