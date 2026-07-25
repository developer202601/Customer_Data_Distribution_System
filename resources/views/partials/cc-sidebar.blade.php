@php
    $ccRouteName   = request()->route()?->getName() ?? '';
    $assignment    = session('user.assignment') ?? '';
    $isSuper       = $assignment === 'super';
    $isSegmentAdmin = str_starts_with($assignment, 'segment_');
    $isCaller       = str_starts_with($assignment, 'caller_');
@endphp
<div class="offcanvas offcanvas-start cc-offcanvas" tabindex="-1" id="ccSidebar" aria-labelledby="ccSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title mb-0" id="ccSidebarLabel">Call Center</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column cc-sidebar-nav">

            @if($isSuper)
                <a class="nav-link{{ $ccRouteName === 'cc.super.segments' ? ' active' : '' }}"
                    href="{{ route('cc.super.segments') }}"
                    aria-current="{{ $ccRouteName === 'cc.super.segments' ? 'page' : '' }}">Segment Admins</a>
                <a class="nav-link{{ str_starts_with($ccRouteName, 'cc.reports') ? ' active' : '' }}"
                    href="{{ route('cc.reports') }}"
                    aria-current="{{ str_starts_with($ccRouteName, 'cc.reports') ? 'page' : '' }}">Reports</a>
                <a class="nav-link{{ $ccRouteName === 'process.assignments.reports' ? ' active' : '' }}"
                    href="{{ route('process.assignments.reports') }}"
                    aria-current="{{ $ccRouteName === 'process.assignments.reports' ? 'page' : '' }}">View Datasets</a>
                <a class="nav-link{{ $ccRouteName === 'admin.config' ? ' active' : '' }}"
                    href="{{ route('admin.config') }}"
                    aria-current="{{ $ccRouteName === 'admin.config' ? 'page' : '' }}">Configurations</a>

            @elseif($isSegmentAdmin)
                <a class="nav-link{{ $ccRouteName === 'cc.segment.dashboard' ? ' active' : '' }}"
                    href="{{ route('cc.segment.dashboard') }}"
                    aria-current="{{ $ccRouteName === 'cc.segment.dashboard' ? 'page' : '' }}">Dashboard</a>
                <a class="nav-link{{ $ccRouteName === 'cc.segment.callers' ? ' active' : '' }}"
                    href="{{ route('cc.segment.callers') }}"
                    aria-current="{{ $ccRouteName === 'cc.segment.callers' ? 'page' : '' }}">Callers</a>
                <a class="nav-link{{ str_starts_with($ccRouteName, 'cc.reports') ? ' active' : '' }}"
                    href="{{ route('cc.reports') }}"
                    aria-current="{{ str_starts_with($ccRouteName, 'cc.reports') ? 'page' : '' }}">Reports</a>

            @elseif($isCaller)
                <a class="nav-link{{ $ccRouteName === 'cc.assignments.manage' ? ' active' : '' }}"
                    href="{{ route('cc.assignments.manage') }}"
                    aria-current="{{ $ccRouteName === 'cc.assignments.manage' ? 'page' : '' }}">Assigned Rows</a>

            @endif

        </nav>
    </div>
</div>
