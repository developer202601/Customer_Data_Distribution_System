@extends('layouts.cc')

@section('title', 'Assign Region')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Assign Supervisor Role for {{ $user->username }}</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.region.assign.index') }}" class="btn btn-outline-success rounded-pill px-4">Assign Supervisors</a>
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

                <form method="POST" action="{{ route('cc.region.assign.store', $user) }}">
        @csrf

        <div class="form-group position-relative">
                    <label for="rtom-input">RTO</label>
                    <input id="rtom-input" type="text" class="form-control" placeholder="Type to search RTOs..." autocomplete="off"
                   value="{{ old('rtom', $user->assignment ? str_replace('rtom_', '', $user->assignment) : '') }}">
            <input id="rtom" name="rtom" type="hidden" value="{{ old('rtom', $user->assignment ? str_replace('rtom_', '', $user->assignment) : '') }}">
            <div id="rtom-suggestions" class="list-group position-absolute w-100" style="z-index:1050; display:none; max-height:320px; overflow:auto;"></div>
        </div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-warning rounded-pill px-4">Save</button>
                    <a href="{{ route('cc.region.assign.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
    // Data from server
    const RTOMS = @json($rtoms->values());

    // RTOM typeahead
    (function(){
        const input = document.getElementById('rtom-input');
        const hidden = document.getElementById('rtom');
        const box = document.getElementById('rtom-suggestions');

        function render(items) {
            if (!items.length) { box.style.display='none'; box.innerHTML=''; return; }
            box.innerHTML = items.map(it => `
                <button type="button" class="list-group-item list-group-item-action">${it}</button>
            `).join('');
            box.style.display='block';
        }

        input.addEventListener('input', function(){
            const q = this.value.trim();
            if (q.length < 1) { box.style.display='none'; return; }
            const matches = RTOMS.filter(r => r.toLowerCase().includes(q.toLowerCase()));
            render(matches);
        });

        input.addEventListener('focus', function(){
            const q = this.value.trim();
            if (q.length >= 1) {
                const matches = RTOMS.filter(r => r.toLowerCase().includes(q.toLowerCase()));
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

@endsection