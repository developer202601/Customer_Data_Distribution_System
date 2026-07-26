@extends('layouts.cc')

@section('title', 'Segment Admins')

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
                        <h1 class="process-upload-title mb-0">Segment Admins</h1>
                        <p class="text-muted small mb-0">Three segments: Call Center Staff, Call Center, Staff.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.super.rb_regions') }}" class="btn btn-outline-secondary rounded-pill px-4">RB Region Admins</a>
                        <a href="{{ route('cc.super.rb_region.create') }}" class="btn btn-outline-primary rounded-pill px-4">Add RB Region Admin</a>
                        <a href="{{ route('cc.users.create') }}" class="btn btn-outline-success rounded-pill px-4">Add Segment Admin</a>
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

                <div class="d-flex gap-2 mb-4 flex-wrap" id="cc-super-segments-filter">
                    <input type="search" name="q" class="form-control form-control-sm" style="max-width:260px;"
                        placeholder="Search username or name" value="{{ $q ?? '' }}">
                    <select name="segment" class="form-select form-select-sm" style="max-width:220px;">
                        <option value="">All Segments</option>
                        @foreach(['segment_ccs' => 'Call Center Staff', 'segment_cc' => 'Call Center', 'segment_s' => 'Staff'] as $val => $lbl)
                            <option value="{{ $val }}" {{ ($selectedSegment ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="table-responsive cc-table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Segment</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cc-segment-admin-rows">
                            @include('cc.super._segment_rows', ['segmentAdmins' => $segmentAdmins])
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Shared hidden forms for disable/enable/delete --}}
<form id="cc-disable-form" method="post" style="display:none">@csrf @method('put')</form>
<form id="cc-enable-form" method="post" style="display:none">@csrf @method('put')</form>
<form id="cc-delete-form" method="post" style="display:none">@csrf @method('delete')</form>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const rowsBody     = document.getElementById('cc-segment-admin-rows');
    const filterDiv    = document.getElementById('cc-super-segments-filter');
    const searchInput  = filterDiv?.querySelector('input[name="q"]');
    const segmentSelect = filterDiv?.querySelector('select[name="segment"]');
    let tick = null;

    const disableForm = document.getElementById('cc-disable-form');
    const enableForm  = document.getElementById('cc-enable-form');
    const deleteForm  = document.getElementById('cc-delete-form');

    function bindRowActions() {
        rowsBody?.querySelectorAll('.cc-disable-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                if (confirm('Disable this user?')) {
                    disableForm.action = btn.dataset.action;
                    disableForm.submit();
                }
            });
        });
        rowsBody?.querySelectorAll('.cc-enable-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                if (confirm('Enable this user?')) {
                    enableForm.action = btn.dataset.action;
                    enableForm.submit();
                }
            });
        });
        rowsBody?.querySelectorAll('.cc-delete-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                if (confirm('Delete this user? This cannot be undone.')) {
                    deleteForm.action = btn.dataset.action;
                    deleteForm.submit();
                }
            });
        });
    }

    function fetchRows() {
        if (!rowsBody) return;
        const params = new URLSearchParams();
        const q = searchInput?.value.trim() ?? '';
        const seg = segmentSelect?.value ?? '';
        if (q) params.set('q', q);
        if (seg) params.set('segment', seg);
        rowsBody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">Loading…</td></tr>';
        fetch('{{ route("cc.super.segments.search") }}?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.text())
            .then(html => { rowsBody.innerHTML = html; bindRowActions(); })
            .catch(() => { rowsBody.innerHTML = '<tr><td colspan="5" class="text-muted">Unable to load.</td></tr>'; });
    }

    function schedule() {
        clearTimeout(tick);
        tick = setTimeout(fetchRows, 250);
    }

    searchInput?.addEventListener('input', schedule);
    segmentSelect?.addEventListener('change', fetchRows);

    bindRowActions();
    fetchRows();
});
</script>
@endpush
@endsection
