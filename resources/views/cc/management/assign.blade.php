@extends('layouts.cc')

@section('title', 'Assign Role')

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
                        <h1 class="process-upload-title mb-0">Assign Role: {{ $user->username }}</h1>
                        <p class="text-muted small mb-0">
                            Current: <strong>{{ $user->name ?: 'No name' }}</strong>
                            &middot; Assignment: <strong>{{ $user->assignment ?: 'none' }}</strong>
                            &middot; Status:
                            @if($user->status)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Disabled</span>
                            @endif
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.management.index') }}" class="btn btn-outline-success rounded-pill px-4">Back to Users</a>
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

                <form method="POST" action="{{ route('cc.management.assign.store', $user) }}">
                    @csrf

                    @php
                        $oldRole = old('role');
                        $selectedRoleLabel = $oldRole ? ($allowedRoles[$oldRole] ?? $oldRole) : '';
                    @endphp
                    <div class="row g-4">
                        {{-- Role Selection --}}
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="role-input" class="form-label">Role <span class="text-danger">*</span></label>
                                <input id="role-input" type="text" class="form-control" placeholder="Click to choose role" autocomplete="off" readonly
                                    value="{{ $selectedRoleLabel }}">
                                <input id="role" name="role" type="hidden" value="{{ old('role') }}">
                                <div id="role-suggestions" class="list-group position-absolute w-100" style="z-index:1050; display:none; max-height:240px; overflow:auto;"></div>
                            </div>
                        </div>

                        {{-- Region Selection (for Region Admin role) --}}
                        <div class="col-md-6" id="region-box" style="display:none">
                            <div class="form-group position-relative">
                                <label for="region-input" class="form-label">Region</label>
                                <input id="region-input" type="text" class="form-control" placeholder="Type to search region..." autocomplete="off"
                                    value="{{ old('region') }}">
                                <input id="region" name="region" type="hidden" value="{{ old('region') }}">
                                <div id="region-suggestions" class="list-group position-absolute w-100" style="z-index:1050; display:none; max-height:320px; overflow:auto;"></div>
                            </div>
                        </div>

                        {{-- RTOM Selection (for RTOM Admin, Supervisor, Caller) --}}
                        <div class="col-md-6" id="rtom-box" style="display:none">
                            <div class="form-group position-relative">
                                <label for="rtom-input" class="form-label">RTOM</label>
                                <input id="rtom-input" type="text" class="form-control" placeholder="Type to search RTOM..." autocomplete="off"
                                    value="{{ old('rtom') }}">
                                <input id="rtom" name="rtom" type="hidden" value="{{ old('rtom') }}">
                                <div id="rtom-suggestions" class="list-group position-absolute w-100" style="z-index:1050; display:none; max-height:320px; overflow:auto;"></div>
                            </div>
                        </div>

                        {{-- Supervisor Selection (for Caller role) --}}
                        <div class="col-md-6" id="supervisor-box" style="display:none">
                            <div class="form-group">
                                <label for="supervisor_id" class="form-label">Assign to Supervisor</label>
                                <select id="supervisor_id" name="supervisor_id" class="form-select">
                                    <option value="">-- Select Supervisor --</option>
                                    @foreach($supervisors as $sup)
                                        <option value="{{ $sup['id'] }}" {{ old('supervisor_id') == $sup['id'] ? 'selected' : '' }}>
                                            {{ $sup['name'] ?? $sup['username'] }} ({{ $sup['username'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Supervisors are filtered by the selected RTOM.</div>
                            </div>
                        </div>

                        {{-- RTOM Admin Selection (for Supervisor role) --}}
                        <div class="col-md-6" id="rtom-admin-box" style="display:none">
                            <div class="form-group">
                                <label for="rtom_admin_id" class="form-label">Assigned RTOM Admin</label>
                                <select id="rtom_admin_id" name="rtom_admin_id" class="form-select">
                                    <option value="">-- Select RTOM Admin --</option>
                                    @foreach($rtomAdmins as $admin)
                                        <option value="{{ $admin['id'] }}" {{ old('rtom_admin_id') == $admin['id'] ? 'selected' : '' }}>
                                            {{ $admin['name'] ?? $admin['username'] }} ({{ $admin['username'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 pt-3">
                        <button class="btn btn-warning rounded-pill px-4">Save Assignment</button>
                        <a href="{{ route('cc.management.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@php
    $allowedRolesJson = [];
    foreach ($allowedRoles as $k => $v) {
        $allowedRolesJson[] = ['key' => $k, 'label' => $v];
    }
@endphp
@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function() {
    // Data from server
    const ALLOWED_ROLES = @json($allowedRolesJson);

    const REGIONS = @json($regions);
    const RTOMS = @json($rtoms);
    const SUPERVISORS = @json($supervisors);

    // Role typeahead
    const roleInput = document.getElementById('role-input');
    const roleHidden = document.getElementById('role');
    const roleBox = document.getElementById('role-suggestions');

    function renderSuggestions(box, items, labelKey) {
        if (!items.length) { box.style.display='none'; box.innerHTML=''; return; }
        box.innerHTML = items.map(it => `
            <button type="button" class="list-group-item list-group-item-action">${labelKey ? it[labelKey] : it}</button>
        `).join('');
        box.style.display='block';
    }

    roleInput.addEventListener('click', () => renderSuggestions(roleBox, ALLOWED_ROLES, 'label'));
    roleInput.addEventListener('focus', () => renderSuggestions(roleBox, ALLOWED_ROLES, 'label'));

    roleBox.addEventListener('click', function(ev) {
        const btn = ev.target.closest('button');
        if (!btn) return;
        const label = btn.textContent.trim();
        const found = ALLOWED_ROLES.find(r => r.label === label);
        if (found) {
            roleInput.value = found.label;
            roleHidden.value = found.key;
        } else {
            roleInput.value = label;
            roleHidden.value = label;
        }
        roleBox.style.display = 'none';
        updateFields();
    });

    document.addEventListener('click', function(e) {
        if (!roleInput.contains(e.target) && !roleBox.contains(e.target)) roleBox.style.display='none';
    });

    // Region typeahead
    const regionInput = document.getElementById('region-input');
    const regionHidden = document.getElementById('region');
    const regionBox = document.getElementById('region-suggestions');

    function filterRegions(q) {
        if (q.length < 1) return REGIONS;
        return REGIONS.filter(r => r.toLowerCase().includes(q.toLowerCase()));
    }

    regionInput.addEventListener('input', function() {
        const matches = filterRegions(this.value.trim());
        renderSuggestions(regionBox, matches, null);
    });
    regionInput.addEventListener('focus', function() {
        const matches = filterRegions(this.value.trim());
        if (matches.length) renderSuggestions(regionBox, matches, null);
    });
    regionBox.addEventListener('click', function(ev) {
        const btn = ev.target.closest('button');
        if (!btn) return;
        const val = btn.textContent.trim();
        regionInput.value = val;
        regionHidden.value = val;
        regionBox.style.display = 'none';
    });
    document.addEventListener('click', function(e) {
        if (!regionInput.contains(e.target) && !regionBox.contains(e.target)) regionBox.style.display='none';
    });

    // RTOM typeahead
    const rtomInput = document.getElementById('rtom-input');
    const rtomHidden = document.getElementById('rtom');
    const rtomBox = document.getElementById('rtom-suggestions');

    function filterRtoms(q) {
        if (q.length < 1) return RTOMS;
        return RTOMS.filter(r => r.toLowerCase().includes(q.toLowerCase()));
    }

    rtomInput.addEventListener('input', function() {
        const matches = filterRtoms(this.value.trim());
        renderSuggestions(rtomBox, matches, null);
    });
    rtomInput.addEventListener('focus', function() {
        const matches = filterRtoms(this.value.trim());
        if (matches.length) renderSuggestions(rtomBox, matches, null);
    });
    rtomBox.addEventListener('click', function(ev) {
        const btn = ev.target.closest('button');
        if (!btn) return;
        const val = btn.textContent.trim();
        rtomInput.value = val;
        rtomHidden.value = val;
        rtomBox.style.display = 'none';
        // Filter supervisors by selected RTOM
        filterSupervisorsByRtom(val);
    });
    document.addEventListener('click', function(e) {
        if (!rtomInput.contains(e.target) && !rtomBox.contains(e.target)) rtomBox.style.display='none';
    });

    // Filter supervisors by RTOM
    function filterSupervisorsByRtom(rtom) {
        const supSelect = document.getElementById('supervisor_id');
        // Clear existing options
        supSelect.innerHTML = '<option value="">-- Select Supervisor --</option>';
        // Filter supervisors whose assignment matches this RTOM
        const rtomKey = rtom.replace(/\s+/g, '_').toLowerCase();
        const filtered = SUPERVISORS.filter(s => {
            // Match supervisor_rtom_{rtom}
            const sAssignment = s.assignment || '';
            return sAssignment.includes('supervisor_rtom_' + rtomKey);
        });
        filtered.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = (s.name || s.username) + ' (' + s.username + ')';
            supSelect.appendChild(opt);
        });
    }

    // Show/hide conditional fields based on selected role
    function updateFields() {
        const role = roleHidden.value;
        const regionBox = document.getElementById('region-box');
        const rtomBox = document.getElementById('rtom-box');
        const supervisorBox = document.getElementById('supervisor-box');
        const rtomAdminBox = document.getElementById('rtom-admin-box');

        regionBox.style.display = role === 'region' ? '' : 'none';
        rtomBox.style.display = (role === 'rtom_admin' || role === 'supervisor' || role === 'caller') ? '' : 'none';
        supervisorBox.style.display = role === 'caller' ? '' : 'none';
        rtomAdminBox.style.display = role === 'supervisor' ? '' : 'none';
    }

    // Also update fields when role changes via the hidden input (for old() values)
    if (roleHidden.value) {
        updateFields();
        if (rtomHidden.value) {
            filterSupervisorsByRtom(rtomHidden.value);
        }
    }
})();
</script>
@endpush
