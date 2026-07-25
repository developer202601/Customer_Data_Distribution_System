@php
    // Expected variables:
    // $mode: 'region' or 'rtom'
    // $action: form action URL
    // $regions: collection/array (for mode 'region')
    // $rtoms: collection/array (for mode 'rtom')
    // $isSupervisor: optional boolean (for RTOM supervisor creation)
    // $systems: optional collection/array (for region mode system selection)
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
        @if(isset($systems) && $systems)
            <div class="form-group mt-3">
                <label for="system">System <span class="text-danger">*</span></label>
                <select id="system" name="system" class="form-select" required>
                    <option value="">-- Select System --</option>
                    @foreach($systems as $key => $label)
                        <option value="{{ $key }}" {{ old('system') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Hidden field that tells the controller which region path was used --}}
        <input type="hidden" name="region_source" id="region_source" value="{{ old('region_source', 'list') }}">

        {{-- List path: autocomplete from last 2 dataset processes --}}
        <div class="form-group position-relative mt-3" id="region-box" @if(old('region_source') === 'custom') style="display:none" @endif>
            <label for="region-input">Region <span class="text-muted small">(from last 2 reports)</span></label>
            <input id="region-input" type="text" class="form-control" placeholder="Type 1-2 characters to search..."
                autocomplete="off"
                value="{{ old('region_source') !== 'custom' ? old('region') : '' }}"
                @if(old('region_source') === 'custom') disabled @endif>
            <input id="region-list-value" type="hidden" value="{{ old('region_source') !== 'custom' ? old('region') : '' }}">
            <div id="region-suggestions" class="list-group position-absolute w-100" style="z-index:1050; display:none; max-height:320px; overflow:auto;"></div>
        </div>

        {{-- Custom path: free-text input --}}
        <div class="form-group mt-3" id="custom-region-box" @if(old('region_source') !== 'custom') style="display:none" @endif>
            <label for="custom-region-input">Custom Region Name <span class="text-danger">*</span></label>
            <input id="custom-region-input" type="text" class="form-control" placeholder="e.g. NORTH CENTRAL"
                maxlength="45"
                value="{{ old('region_source') === 'custom' ? old('region') : '' }}"
                @if(old('region_source') !== 'custom') disabled @endif>
            <div class="form-text text-muted">Enter any region name not listed above.</div>
        </div>

        {{-- The actual submitted region value, kept in sync by JS --}}
        <input type="hidden" name="region" id="region-hidden" value="{{ old('region') }}">

        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="custom-region-toggle"
                {{ old('region_source') === 'custom' ? 'checked' : '' }}>
            <label class="form-check-label" for="custom-region-toggle">
                Enter a custom region not in the list
            </label>
        </div>

    @elseif($mode === 'rtom')
        <div class="form-group mt-3">
            <label for="rtom">RTO</label>
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
    <script nonce="{{ $cspNonce ?? '' }}">
        const REGIONS = @json($regions->values());
        (function(){
            const input       = document.getElementById('region-input');
            const listValue   = document.getElementById('region-list-value');
            const regionHidden = document.getElementById('region-hidden');
            const sourceHidden = document.getElementById('region_source');
            const box         = document.getElementById('region-suggestions');
            const regionBox   = document.getElementById('region-box');
            const customBox   = document.getElementById('custom-region-box');
            const customInput = document.getElementById('custom-region-input');
            const toggle      = document.getElementById('custom-region-toggle');

            // --- Autocomplete list logic ---
            function render(items) {
                if (!items.length) { box.style.display='none'; box.innerHTML=''; return; }
                box.innerHTML = items.map(it =>
                    `<button type="button" class="list-group-item list-group-item-action">${it}</button>`
                ).join('');
                box.style.display = 'block';
            }

            input.addEventListener('input', function(){
                const q = this.value.trim();
                listValue.value = '';
                regionHidden.value = '';
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
                listValue.value = val;
                regionHidden.value = val;
                box.style.display = 'none';
            });

            document.addEventListener('click', function(e){
                if (!input.contains(e.target) && !box.contains(e.target)) {
                    box.style.display = 'none';
                }
            });

            // --- Custom region toggle logic ---
            toggle.addEventListener('change', function(){
                if (this.checked) {
                    // Switch to custom path
                    regionBox.style.display   = 'none';
                    customBox.style.display   = '';
                    input.disabled            = true;
                    customInput.disabled      = false;
                    sourceHidden.value        = 'custom';
                    regionHidden.value        = customInput.value.trim();
                } else {
                    // Switch back to list path
                    regionBox.style.display   = '';
                    customBox.style.display   = 'none';
                    input.disabled            = false;
                    customInput.disabled      = true;
                    sourceHidden.value        = 'list';
                    regionHidden.value        = listValue.value;
                }
            });

            // Keep hidden region in sync as the custom input is typed
            customInput.addEventListener('input', function(){
                if (!this.disabled) {
                    regionHidden.value = this.value.trim();
                }
            });

            // Sync on list-input blur in case user typed without picking a suggestion
            input.addEventListener('blur', function(){
                if (!this.disabled && listValue.value === '' && this.value.trim() !== '') {
                    // user typed but didn't pick — clear to avoid submitting unvalidated text
                    // (they must pick from the dropdown on the list path)
                    regionHidden.value = '';
                } else if (!this.disabled) {
                    regionHidden.value = listValue.value;
                }
            });
        })();
    </script>
    @endpush
@endif