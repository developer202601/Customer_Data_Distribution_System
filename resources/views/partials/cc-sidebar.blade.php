<div class="offcanvas offcanvas-start cc-offcanvas" tabindex="-1" id="ccSidebar" aria-labelledby="ccSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title mb-0" id="ccSidebarLabel">Call Center</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column cc-sidebar-nav">
            <a class="nav-link" href="{{ route('cc.dashboard') }}">Overview</a>
            <a class="nav-link" href="{{ route('cc.users.index') }}">Manage Users</a>
            <a class="nav-link" href="{{ route('cc.reports') }}">Reports</a>
            <a class="nav-link" href="#">Queues</a>
        </nav>
    </div>
</div>
