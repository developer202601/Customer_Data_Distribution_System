@extends('layouts.cc')

@section('title', 'RB Region Admins')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">RB Region Admins</h1>
                        <p class="text-muted small mb-0">Regional Billing region admin accounts managed from this dashboard.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.super.rb_region.create') }}" class="btn btn-outline-success rounded-pill px-4">Add RB Region Admin</a>
                        <a href="{{ route('cc.super.segments') }}" class="btn btn-outline-secondary rounded-pill px-4">Back to Segments</a>
                    </div>
                </div>

                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="d-flex gap-2 mb-4 flex-wrap" id="cc-rb-regions-filter">
                    <input type="search" name="q" class="form-control form-control-sm" style="max-width:260px;"
                        placeholder="Search username or name" value="{{ $q ?? '' }}">
                    <select name="region" class="form-select form-select-sm" style="max-width:220px;">
                        <option value="">All Regions</option>
                        @foreach($regions as $r)
                            <option value="{{ $r }}" {{ ($selectedRegion ?? '') === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Region</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cc-rb-region-rows">
                            @include('cc.super._rb_region_rows', ['regionAdmins' => $regionAdmins])
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const rowsBody     = document.getElementById('cc-rb-region-rows');
    const filterDiv    = document.getElementById('cc-rb-regions-filter');
    const searchInput  = filterDiv?.querySelector('input[name="q"]');
    const regionSelect = filterDiv?.querySelector('select[name="region"]');
    let tick = null;

    function fetchRows() {
        if (! rowsBody) return;
        const params = new URLSearchParams();
        const q = searchInput?.value.trim() ?? '';
        const region = regionSelect?.value ?? '';
        if (q) params.set('q', q);
        if (region) params.set('region', region);
        rowsBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">Loading…</td></tr>';
        fetch('{{ route("cc.super.rb_regions.search") }}?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.text())
            .then(html => { rowsBody.innerHTML = html; })
            .catch(() => { rowsBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Unable to load.</td></tr>'; });
    }

    function schedule() {
        clearTimeout(tick);
        tick = setTimeout(fetchRows, 250);
    }

    searchInput?.addEventListener('input', schedule);
    regionSelect?.addEventListener('change', fetchRows);
});
</script>
@endpush
@endsection
