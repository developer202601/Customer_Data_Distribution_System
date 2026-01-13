@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Create New Region User</h1>
                        <p class="text-muted small mb-0">Creates a region admin user (Region Admin) and assigns them to a region from the last two reports.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('cc.users.create.store') }}">
        @csrf

        <div class="form-group">
            <label for="username">Username <span class="text-danger">*</span></label>
            <input id="username" name="username" type="text" class="form-control" value="{{ old('username') }}" maxlength="6" placeholder="Enter 6-digit username" required>
        </div>

        <div class="form-group position-relative">
            <label for="role-input">Privilege</label>
                 <input id="role-input" type="text" class="form-control" placeholder="Region Admin" autocomplete="off" readonly value="Region Admin">
            <input id="role" name="role" type="hidden" value="region">
        </div>

        <div class="form-group" id="region-box" style="margin-top:1rem;">
            <label for="region-input">Region (from last 2 reports)</label>
            <input id="region-input" type="text" class="form-control" placeholder="Type 1-2 characters to search..." autocomplete="off"
                   value="{{ old('region') }}">
            <input id="region" name="region" type="hidden" value="{{ old('region') }}">
            <div id="region-suggestions" class="list-group position-absolute w-100" style="z-index:1050; display:none; max-height:320px; overflow:auto;"></div>
        </div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-warning rounded-pill px-4">Create User</button>
                    <a href="{{ route('cc.users.assign.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Region suggestions (matches behavior in assign form)
    const REGIONS = @json($regions->values());
    (function(){
        const input = document.getElementById('region-input');
        const hidden = document.getElementById('region');
        const box = document.getElementById('region-suggestions');

        function render(items) {
            if (!items.length) { box.style.display='none'; box.innerHTML=''; return; }
            box.innerHTML = items.map(it => `\n                <button type="button" class="list-group-item list-group-item-action">${it}</button>\n            `).join('');
            box.style.display='block';
        }

        input.addEventListener('input', function(){
            const q = this.value.trim();
            if (q.length < 1) { box.style.display='none'; return; }
            const matches = REGIONS.filter(r => r.toLowerCase().includes(q.toLowerCase()));
            render(matches);
        });

        input.addEventListener('focus', function(){
            const q = this.value.trim();
            if (q.length >= 1) {
                const matches = REGIONS.filter(r => r.toLowerCase().includes(q.toLowerCase()));
                render(matches);
            }
        });

        box.addEventListener('click', function(ev){
            const btn = ev.target.closest('button');
            if (!btn) return;
            const val = btn.textContent.trim();
            input.value = val;
            hidden.value = val;
            box.style.display='none';
        });

        document.addEventListener('click', function(e){ if (!input.contains(e.target) && !box.contains(e.target)) box.style.display='none'; });
    })();
</script>
@endpush