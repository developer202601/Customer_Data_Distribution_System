@php
    // Expected variables:
    // $mode: 'region' or 'rtom'
    // $action: form action URL
    // $regions: collection/array (for mode 'region')
    // $rtoms: collection/array (for mode 'rtom')
    // $isSupervisor: optional boolean (for RTOM supervisor creation)
@endphp

<form method="POST" action="{{ $action }}">
    @csrf

    <!-- Do not auto-mark newly created users as fixed; default to 0 in controller -->

    <div class="form-group">
        <label for="username">Username <span class="text-danger">*</span></label>
        <input id="username" name="username" type="text" class="form-control" value="{{ old('username') }}" maxlength="6" placeholder="Enter 6-digit username" required>
    </div>

    <div class="form-group mt-3">
        <label for="name">Name</label>
        <input id="name" name="name" type="text" class="form-control" value="{{ old('name') }}">
    </div>

    @if($mode === 'region')
        <div class="form-group position-relative mt-3" id="region-box">
            <label for="region-input">Region (from last 2 reports)</label>
            <input id="region-input" type="text" class="form-control" placeholder="Type 1-2 characters to search..." autocomplete="off" value="{{ old('region') }}">
            <input id="region" name="region" type="hidden" value="{{ old('region') }}">
            <div id="region-suggestions" class="list-group position-absolute w-100" style="z-index:1050; display:none; max-height:320px; overflow:auto;"></div>
        </div>
    @elseif($mode === 'rtom')
        <div class="form-group mt-3">
            <label for="rtom">RTOM</label>
            <select id="rtom" name="rtom" class="form-select">
                @foreach($rtoms as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-warning rounded-pill px-4">{{ $mode === 'rtom' ? 'Create' : 'Create User' }}</button>
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary px-4">Cancel</a>
    </div>
</form>

@if($mode === 'region')
    @push('scripts')
    <script>
        const REGIONS = @json($regions->values());
        (function(){
            const input = document.getElementById('region-input');
            const hidden = document.getElementById('region');
            const box = document.getElementById('region-suggestions');

            function render(items) {
                if (!items.length) { box.style.display='none'; box.innerHTML=''; return; }
                box.innerHTML = items.map(it => `\n                    <button type="button" class="list-group-item list-group-item-action">${it}</button>\n                `).join('');
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
@endif