{{--
  Shared region admin creation form.
  Expected variables:
    $action  : form POST URL
    $regions : Collection of region names from last two dataset runs
    $backUrl : URL for the Back / Cancel button
--}}

<form method="post" action="{{ $action }}">
    @csrf

    <div class="form-group mb-3">
        <label for="username">Username <span class="text-danger">*</span></label>
        <input id="username" name="username" type="text" class="form-control"
            value="{{ old('username') }}" maxlength="6"
            placeholder="6-digit staff ID" required>
    </div>

    <div class="form-group mb-3">
        <label for="name">Name</label>
        <input id="name" name="name" type="text" class="form-control"
            value="{{ old('name') }}">
    </div>

    {{-- Hidden field telling the controller which path was used --}}
    <input type="hidden" name="region_source" id="region_source"
        value="{{ old('region_source', 'list') }}">

    {{-- List path: autocomplete from last 2 dataset processes --}}
    <div class="form-group position-relative mb-3" id="region-box"
        @if(old('region_source') === 'custom') style="display:none" @endif>
        <label for="region-input">
            Region <span class="text-muted small">(from last 2 reports)</span>
        </label>
        <input id="region-input" type="text" class="form-control"
            placeholder="Type to search…" autocomplete="off"
            value="{{ old('region_source') !== 'custom' ? old('region') : '' }}"
            @if(old('region_source') === 'custom') disabled @endif>
        <input id="region-list-value" type="hidden"
            value="{{ old('region_source') !== 'custom' ? old('region') : '' }}">
        <div id="region-suggestions"
            class="list-group position-absolute w-100"
            style="z-index:1050; display:none; max-height:320px; overflow:auto;"></div>
    </div>

    {{-- Custom path: free-text --}}
    <div class="form-group mb-3" id="custom-region-box"
        @if(old('region_source') !== 'custom') style="display:none" @endif>
        <label for="custom-region-input">
            Custom Region Name <span class="text-danger">*</span>
        </label>
        <input id="custom-region-input" type="text" class="form-control"
            placeholder="e.g. NORTH CENTRAL" maxlength="45"
            value="{{ old('region_source') === 'custom' ? old('region') : '' }}"
            @if(old('region_source') !== 'custom') disabled @endif>
        <div class="form-text text-muted">Enter any region name not in the list above.</div>
    </div>

    {{-- Actual submitted region value — kept in sync by JS --}}
    <input type="hidden" name="region" id="region-hidden" value="{{ old('region') }}">

    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="custom-region-toggle"
            {{ old('region_source') === 'custom' ? 'checked' : '' }}>
        <label class="form-check-label" for="custom-region-toggle">
            Enter a custom region not in the list
        </label>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-warning rounded-pill px-4">Create</button>
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary px-4">Cancel</a>
    </div>
</form>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    const REGIONS = @json($regions->values());

    const regionInput   = document.getElementById('region-input');
    const listValue     = document.getElementById('region-list-value');
    const regionHidden  = document.getElementById('region-hidden');
    const sourceHidden  = document.getElementById('region_source');
    const suggestBox    = document.getElementById('region-suggestions');
    const regionBox     = document.getElementById('region-box');
    const customBox     = document.getElementById('custom-region-box');
    const customInput   = document.getElementById('custom-region-input');
    const toggle        = document.getElementById('custom-region-toggle');

    function renderSuggestions(items) {
        if (!items.length) { suggestBox.style.display = 'none'; suggestBox.innerHTML = ''; return; }
        suggestBox.innerHTML = items
            .map(r => `<button type="button" class="list-group-item list-group-item-action">${r}</button>`)
            .join('');
        suggestBox.style.display = 'block';
    }

    regionInput?.addEventListener('input', function () {
        listValue.value    = '';
        regionHidden.value = '';
        const q = this.value.trim();
        if (q.length < 1) { suggestBox.style.display = 'none'; return; }
        renderSuggestions(REGIONS.filter(r => r.toLowerCase().includes(q.toLowerCase())));
    });

    regionInput?.addEventListener('focus', function () {
        const q = this.value.trim();
        if (q.length >= 1) renderSuggestions(REGIONS.filter(r => r.toLowerCase().includes(q.toLowerCase())));
    });

    suggestBox?.addEventListener('click', function (ev) {
        const btn = ev.target.closest('button');
        if (!btn) return;
        const val = btn.textContent.trim();
        regionInput.value  = val;
        listValue.value    = val;
        regionHidden.value = val;
        suggestBox.style.display = 'none';
    });

    document.addEventListener('click', function (e) {
        if (!regionInput?.contains(e.target) && !suggestBox?.contains(e.target)) {
            if (suggestBox) suggestBox.style.display = 'none';
        }
    });

    regionInput?.addEventListener('blur', function () {
        if (!this.disabled) {
            regionHidden.value = listValue.value;
        }
    });

    customInput?.addEventListener('input', function () {
        if (!this.disabled) regionHidden.value = this.value.trim();
    });

    toggle?.addEventListener('change', function () {
        if (this.checked) {
            regionBox.style.display    = 'none';
            customBox.style.display    = '';
            regionInput.disabled       = true;
            customInput.disabled       = false;
            sourceHidden.value         = 'custom';
            regionHidden.value         = customInput.value.trim();
        } else {
            regionBox.style.display    = '';
            customBox.style.display    = 'none';
            regionInput.disabled       = false;
            customInput.disabled       = true;
            sourceHidden.value         = 'list';
            regionHidden.value         = listValue.value;
        }
    });
})();
</script>
@endpush
