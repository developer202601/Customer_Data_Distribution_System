@extends('layouts.admin')

@section('title', 'Configurations')

@section('navbar-right')
@if(session('user.is_admin'))
<a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mr-2">Return To Main</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection



@section('content')
<div class="admin-config-page-con">
    <div class="admin-config-visual-panel">
        <div class="admin-config-visual-inner">
            <div class="admin-config-layout">
                <div class="admin-config-left width-auto">
                    <h1 class="admin_config-title">Configurations</h1>
                    <div class="admin-config-btn-col config-admin-btn">
                        <button type="button" class="admin-config-btn is-active config-side-btn button" data-config-target="latest-bill-range">Bill Value Range</button>
                        <button type="button" class="admin-config-btn button" data-config-target="bill-arears-quota">No Of Accounts</button>
                        <button type="button" class="admin-config-btn button" data-config-target="user-account">User Account</button>
                    </div>
                </div>

                <div class="admin-config-right">
                        <form action="{{ route('configurations.billrange') }}" method="POST" class="card shadow-sm border-0" novalidate>
                            @csrf
                            @method('post')
                            <input type="hidden" name="tab" value="latest-bill-range" />
                        <div class="admin-config-form is-active bill_range-config" data-config-block="latest-bill-range">
                            <div class="card-body config-card">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <p class="admin-config-hint mb-1">Here you can change the current values.</p>
                                        <p class="text-muted mb-0 small">Current values load from the database.</p>
                                        @if(!empty($billRangeUpdated['timestamp']))
                                            <div class="small text-muted mt-2">
                                                <div>Last edited: {{ optional($billRangeUpdated['timestamp'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                                                <div>By: {{ $billRangeUpdated['editor']->username ?? $billRangeUpdated['editor']->name ?? 'Unknown' }}</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="admin-config-field">
                                    <label class="bill-upper" for="upper_range">Upper Range :</label>
                                    <input type="number" class="admin-config-input" min="0" name="upper_range" id="upper_range" value="{{ $configs['upper_range']->value ?? '' }}" placeholder="Current value" required />
                                </div>

                                <div class="admin-config-field">
                                    <label class="bill-lower" for="lower_range">Lower Range :</label>
                                    <input type="number" class="admin-config-input" min="0" name="lower_range" id="lower_range" value="{{ $configs['lower_range']->value ?? '' }}" placeholder="Current value" required />
                                </div>


                                <button type="submit" class="btn btn-primary px-4 admin-config-save-btn">Save</button>
                            </div>
                        </div>
                    </form>

                    <form action="{{ route('configurations.billarears') }}" method="POST" class="card shadow-sm border-0">
                        @csrf
                        @method('post')
                        <input type="hidden" name="tab" value="bill-arears-quota" />
                        <div class="admin-config-form" data-config-block="bill-arears-quota">
                            <div class="card-body config-card">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <p class="admin-config-hint mb-1">Change the Bill Areas quota.</p>
                                        <p class="text-muted mb-0 small">Current values load from the database.</p>
                                        @if(!empty($staffUpdated['timestamp']))
                                            <div class="small text-muted mt-2">
                                                <div>Last edited: {{ optional($staffUpdated['timestamp'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                                                <div>By: {{ $staffUpdated['editor']->username ?? $staffUpdated['editor']->name ?? 'Unknown' }}</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="admin-config-field admin_config_staff">
                                    <label for="call-centre-staff" class="config-bill-areas">Call Centre Staff :</label>
                                    <input type="text" class="admin-config-input" name="ccs" id="call-centre-staff" value="{{ $configs['ccs']->value ?? '' }}" placeholder="Enter Call Centre Staff" />
                                </div>

                                <div class="admin-config-field admin_config_staff">
                                    <label for="call-centre" class="config-bill-areas">Call Centre :</label>
                                    <input type="text" class="admin-config-input" name="cc" id="call-centre" value="{{ $configs['cc']->value ?? '' }}" placeholder="Enter Call Centre" />
                                </div>

                                <div class="admin-config-field  admin_config_staff">
                                    <label for="staff" class="config-bill-areas">Staff :</label>
                                    <input type="text" class="admin-config-input" name="s" id="staff" value="{{ $configs['s']->value ?? '' }}" placeholder="Enter Staff" />
                                </div>

                                <button type="submit" class="btn btn-primary px-4 admin-config-save-btn">Save</button>
                            </div>
                        </div>
                    </form>

                    <div class="admin-config-form" data-config-block="user-account">
                        <div class="config-card">
                            <p class="admin-config-hint">Here you can change the user account</p>

                            <div class="user-account-panel">
                                <div class="user-account-add">
                                    <input type="text" id="user-account-input" class="user-acc-input admin-config-input" placeholder="Enter 6-digit ID" inputmode="numeric" autocomplete="off" />
                                    <button type="button" class="btn btn-primary user-account-add-btn">Add</button>
                                </div>

                                <div class="user-account-list" aria-live="polite">
                                    @foreach($users as $user)
                                    <div class="user-account-item" data-user-id="{{ $user->id }}" data-username="{{ $user->username }}" data-blocked="{{ $user->status ? 'false' : 'true' }}">
                                        <div class="user-account-item-label">
                                            <span class="user-account-display-name">{{ $user->name ?? $user->username }}</span>
                                            <span class="user-account-username text-muted small">({{ $user->username }})</span>
                                        </div>
                                        <div class="user-account-item-controls">
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill user-account-item-edit">Edit</button>
                                            <button type="button" class="btn btn-sm rounded-pill user-account-item-block {{ $user->status ? 'btn-warning' : 'btn-success' }}">{{ $user->status ? 'Block' : 'Unblock' }}</button>
                                            @if(empty($user->has_generated_reports))
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill user-account-item-remove">Delete</button>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>

                                <div class="user-account-actions">
                                    <div class="user-account-save-status" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Edit display name modal (replaces browser prompt so it doesn't show "localhost says") -->
<div class="modal fade" id="adminUserEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit display name</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="adminUserEditInput" class="form-label">Display name</label>
                <input id="adminUserEditInput" type="text" class="form-control" maxlength="255" />
                <div class="form-text text-muted">Username will not change.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="adminUserEditSave">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete user modal (replaces browser confirm so it doesn't show "localhost says") -->
<div class="modal fade" id="adminUserDisableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete user</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this user?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="adminUserDisableConfirm">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection  

<style nonce="{{ $cspNonce ?? '' }}">

    .bill-upper .bill-lower label{
        color: var(--text-primary);
    }

    .admin-config-save-btn{
        justify-content: center;
        margin-left: 170px;
        margin-top: 30px;
    }

    .admin_config_staff{
        color: var(--text-primary);
    }

    :root {
        --config-border: var(--surface-border);
    }

    .config-card {
        border: 0;
        border-radius: 0;
        padding: 0;
        background: transparent;
        box-shadow: none;
    }

    .config-card h5 {
        font-weight: 600;
    }

    

    


    .config-bill-areas{
        color: var(--text-primary);
    }

    .bill-upper, .bill-lower{  
        color: var(--text-primary);
    }

    .config-admin-btn .button{
        margin-bottom: 30px;
    }


    .admin-config-field input {
        width: 100%;   /* or 300px */
        padding: 10px;
        border-radius: 50px;
    }


    .admin-config-field {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
    }

    .admin-config-field label {
        width: 140px;      /* Adjust label width */
    }




    .admin-config-field .input{
        margin: 50px;
    }

    .staff-submit-btn{
        margin-top: 40px;
        text-align-last: center;
        padding: 0px 20px;
        gap: 20px;
        margin-left: 160px;
    }
    
    /* .admin_config_staff{
        margin-top: 50px;
    } */

    .config-admin-btn{
        margin-top: 40px;
        text-align-last: center;
        padding: 0px 20px;
        gap: 20px;
    }

    

    .admin-config-layout {
        display: flex;
        align-items: flex-start;
        gap: clamp(3rem, 5vw, 6rem);
    }

    .admin-config-left {
        display: flex;
        flex-direction: column;
        gap: 24px;
        min-width: 240px;
        max-width: 280px;
    }

    .admin_config-title {
        margin: 0;
        line-height: 1.1;
    }

    .admin-config-btn-col {
        display: grid;
        gap: 16px;
    }

    .admin-config-btn-col .admin-config-btn {
        padding: 12px 16px;
        font-size: 16px;
        border: 1px solid var(--surface-border);
        background: var(--surface-card);
        color: var(--text-primary);
        border-radius: 12px;
        cursor: pointer;
        transition: 0.2s ease;
        font-weight: 600;
        text-align: left;
        box-shadow: var(--shadow-soft);
    }

    .admin-config-btn-col .admin-config-btn:hover {
        background-color: var(--surface-muted);
        color: var(--accent-primary);
        border-color: rgba(0, 86, 162, 0.35);
    }

    .admin-config-btn.is-active {
        background: var(--accent-primary);
        color: #fff;
        border-color: var(--accent-primary);
        box-shadow: var(--shadow-soft);
    }

    .admin-config-right {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: stretch;
        position: relative;
    }

    .admin-config-form {
        display: none;
        flex-direction: column;
        gap: 32px;
        /* width: min(520px, 100%); */
        padding: clamp(1.75rem, 4vw, 2.5rem);
        border-radius: 24px;
        background: var(--surface-card);
        border: 1px solid var(--surface-border);
        box-shadow: 0 0 0 rgba(0, 0, 0, 0);
        transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;  
    }

    .admin-config-form.is-active {
        display: flex;
        background: var(--surface-card);
        border-color: var(--surface-border);
        box-shadow: var(--shadow-soft);
    }

    .admin-config-visual-panel {
        background-color: transparent;
        color: var(--text-primary);
        border-right: none;
    }

    .admin-config-visual-inner {
        border-color: var(--surface-border);
        border-width: thin;
        padding: clamp(5rem, 4vw, 3.5rem);
        background: var(--surface-card);
        border-radius: 32px;
        margin: 20px;
    }

    .admin-config-field {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .admin-config-field label {
        width: 160px;
        font-weight: 500;
        font-size: 17px;
    }

    .admin-config-label-two-line {
        line-height: 1.2;
    }

    .admin-config-field input {
        flex: 1;
        padding: 8px 25px;
        border: 1px solid var(--surface-border);
        border-radius: 10px;
        background: var(--surface-card);
        color: var(--text-primary);
    }

    /* Hide number input spinner controls (keep numeric type) */
    .admin-config-form input[type="number"]::-webkit-outer-spin-button,
    .admin-config-form input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .admin-config-form input[type="number"] {
        -moz-appearance: textfield;
    }

    .admin-config-placeholder {
        margin: 0;
        text-align: center;
        color: var(--text-secondary);
        font-size: 16px;
    }

    .admin-config-hint {
        margin: 0 0 16px 0;
        color: var(--text-muted);
        font-size: 15px;
        font-weight: 600;
        text-align: left;
        background: var(--surface-muted);
        padding: 10px 14px;
        border-radius: 8px;
        border: 1px solid var(--surface-border);
    }

    /* User account list styles (matches attachment) */
    .user-account-panel {
        margin-top: 8px;
    }

    .user-account-add {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        align-items: center;
        margin-bottom: 12px;
        margin-right: 70px;
    }

    .user-account-add input {
        padding: 8px 10px;
        border: 1px solid var(--surface-border);
        border-radius: 6px;
        min-width: 220px;
        background: var(--surface-card);
        color: var(--text-primary);
    }

    .user-account-add-btn {
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid var(--accent-primary);
        background: var(--accent-primary);
        color: #fff;
        cursor: pointer;
    }

    .user-account-list {
        border: 1px solid var(--surface-border);
        padding: 12px;
        border-radius: 6px;
        min-height: 72px;
        background: var(--surface-muted);
    }

    .user-account-item {
        background: var(--surface-card);
        padding: 10px 12px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 6px;
        border: 1px solid var(--surface-border);
    }

    .user-account-item-controls {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-left: auto;
    }

    

    .user-account-item.blocked {
        opacity: 0.6;
        background: var(--surface-muted);
        text-decoration: line-through;
    }

    .user-account-item:last-child {
        margin-bottom: 0;
    }

    

    

    /* Center save area in user account panel */
    .user-account-actions {

                                        

        margin-top: 12px;
        display: flex;
        justify-content: center;
        gap: 12px;
        align-items: center;
    }

    /* Dark mode: make specific labels white for better contrast */
    /* legacy rule kept commented for reference
    @media (prefers-color-scheme: dark) {
        label[for="call-centre"].config-bill-areas,
        label[for="call-centre-staff"].config-bill-areas,
        label[for="upper_range"].bill-upper,
        label[for="lower_range"].bill-lower {
            color: #ffffff;
        }
    }
    */

   

    .user-account-save-status {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .config-btn-range {
        padding: 10px 40px;
        background: var(--accent-primary);
        color: #fff;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        font-weight: bold;
        align-self: center;
        
    }

    .config-btn-range:hover {
        background: var(--btn-primary-hover-bg);
    }

    /* ============================
   MOBILE RESPONSIVE SECTION
   ============================ */
    @media (max-width: 1024px) {
        .admin-config-layout {
            flex-direction: column;
            gap: 2.5rem;
        }

        .admin-config-left {
            max-width: 100%;
        }

        .admin-config-right {
            width: 100%;
        }
    }

    @media (max-width: 768px) {
        .admin-config-visual-inner {
            padding: 2.5rem 1.75rem;
            margin: 10px;
        }

        .admin-config-btn-col {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }

        .admin-config-field {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .admin-config-field label {
            width: 100%;
            font-size: 16px;
        }

        .admin-config-field input {
            width: 100%;
        }

        .config-btn-range {
            width: 100%;
            text-align: center;
            padding: 12px;
        }
        
        .staff-submit-btn{
        margin-top: 40px;
        text-align-last: center;
        padding: 12px;
        gap: 20px;
        margin-left: 0px;
    }
    }

    @media (max-width: 480px) {
        .admin-config-btn-col {
            grid-template-columns: 1fr;
        }

        .config-btn-range {
            padding: 10px;
        }


        .user-account-add input {
            min-width: 100px;
            
        }
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: var(--surface-card);
        margin: 15% auto;
        padding: 20px;
        border: 1px solid var(--surface-border);
        width: 80%;
        max-width: 400px;
        border-radius: 8px;
        text-align: center;
        color: var(--text-primary);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
    }

    
    
</style>



<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function() {
        var buttons = document.querySelectorAll('.admin-config-btn[data-config-target]');
        var blocks = document.querySelectorAll('.admin-config-form[data-config-block]');

        function setTabInUrl(target) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', target);
                window.history.replaceState({}, '', url.toString());
            } catch (e) {
                // ignore
            }
        }

        function activate(target) {
            if (!target) {
                return;
            }

            buttons.forEach(function(button) {
                var matches = button.getAttribute('data-config-target') === target;
                button.classList.toggle('is-active', matches);
            });

            blocks.forEach(function(block) {
                var matches = block.getAttribute('data-config-block') === target;
                block.classList.toggle('is-active', matches);
            });

            setTabInUrl(target);
        }

        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                activate(button.getAttribute('data-config-target'));
            });
        });

        var urlTab = null;
        try {
            urlTab = new URLSearchParams(window.location.search).get('tab');
        } catch (e) {
            urlTab = null;
        }

        var oldTab = @json(old('tab'));
        var initialTarget = urlTab || oldTab || null;

        if (initialTarget) {
            activate(initialTarget);
            return;
        }

        var initialButton = document.querySelector('.admin-config-btn.is-active');
        if (initialButton) {
            activate(initialButton.getAttribute('data-config-target'));
        } else if (buttons.length) {
            activate(buttons[0].getAttribute('data-config-target'));
        }
    });
</script>  
<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function() {
        var addBtn = document.querySelector('.user-account-add-btn');
        var input = document.getElementById('user-account-input');
        var list = document.querySelector('.user-account-list');
        var saveStatus = document.querySelector('.user-account-save-status');

        var editModalEl = document.getElementById('adminUserEditModal');
        var editInputEl = document.getElementById('adminUserEditInput');
        var editSaveBtn = document.getElementById('adminUserEditSave');
        var editModal = (window.bootstrap && editModalEl) ? new bootstrap.Modal(editModalEl) : null;
        var editingRow = null;
        var editingLabelEl = null;

        var disableModalEl = document.getElementById('adminUserDisableModal');
        var disableConfirmBtn = document.getElementById('adminUserDisableConfirm');
        var disableModal = (window.bootstrap && disableModalEl) ? new bootstrap.Modal(disableModalEl) : null;
        var disablingRow = null;

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        if (addBtn && input && list) {
            function setSaveStatus(message) {
                if (saveStatus) saveStatus.textContent = message || '';
            }

            function normalizeUsername(val) {
                return String(val || '').trim();
            }

            function isSixDigitUsername(val) {
                return /^\d{6}$/.test(val);
            }

            function cssEscape(val) {
                if (window.CSS && typeof window.CSS.escape === 'function') {
                    return window.CSS.escape(val);
                }
                return String(val).replace(/[^a-zA-Z0-9_\-]/g, function (ch) {
                    return '\\' + ch;
                });
            }

            function hasUsernameInList(username) {
                var needle = normalizeUsername(username);
                if (!needle) return false;
                return !!list.querySelector('.user-account-item[data-username="' + cssEscape(needle) + '"]');
            }

            function createItem(user) {
                var item = document.createElement('div');
                item.className = 'user-account-item';
                item.setAttribute('data-user-id', user.id);
                item.setAttribute('data-username', user.username);
                item.setAttribute('data-blocked', user.status ? 'false' : 'true');
                item.innerHTML = '<div class="user-account-item-label">' +
                    '<span class="user-account-display-name">' + escapeHtml(user.name || user.username) + '</span>' +
                    '<span class="user-account-username text-muted small">(' + escapeHtml(user.username) + ')</span>' +
                    '</div>' +
                    '<div class="user-account-item-controls">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary rounded-pill user-account-item-edit">Edit</button>' +
                    '<button type="button" class="btn btn-sm rounded-pill user-account-item-block ' + (user.status ? 'btn-warning' : 'btn-success') + '">' + (user.status ? 'Block' : 'Unblock') + '</button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill user-account-item-remove">Delete</button>' +
                    '</div>';
                return item;
            }

            addBtn.addEventListener('click', function() {
                var val = normalizeUsername(input.value);
                if (!val) return;

                if (!isSixDigitUsername(val)) {
                    setSaveStatus('Please enter a 6-digit ID');
                    input.focus();
                    return;
                }

                if (hasUsernameInList(val)) {
                    setSaveStatus('This ID is already added');
                    input.select();
                    return;
                }

                addBtn.disabled = true;
                setSaveStatus('Adding...');

                fetch('/admin/users', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ username: val })
                })
                .then(async function (response) {
                    var payload = null;
                    try { payload = await response.json(); } catch (e) { payload = null; }
                    if (!response.ok) {
                        var message = 'Failed to add user';
                        if (payload && payload.errors && payload.errors.username && payload.errors.username.length) {
                            message = payload.errors.username[0];
                        }
                        throw new Error(message);
                    }
                    return payload;
                })
                .then(function (data) {
                    if (!data || !data.success || !data.user) {
                        throw new Error('Failed to add user');
                    }

                    if (hasUsernameInList(data.user.username)) {
                        setSaveStatus('This ID is already added');
                        return;
                    }

                    var item = createItem(data.user);
                    list.appendChild(item);
                    setSaveStatus('Added');
                    setTimeout(function () { setSaveStatus(''); }, 1500);
                })
                .catch(function (error) {
                    console.error('Error:', error);
                    setSaveStatus(error && error.message ? error.message : 'Failed to add user');
                })
                .finally(function () {
                    addBtn.disabled = false;
                });

                input.value = '';
                input.focus();
            });

            list.addEventListener('click', function(e) {
                var target = e.target;

                if (target.classList.contains('user-account-item-edit')) {
                    var row = target.closest('.user-account-item');
                    if (!row) return;
                    var labelEl = row.querySelector('.user-account-item-label');
                    var nameEl = row.querySelector('.user-account-display-name');
                    var current = nameEl ? nameEl.textContent.trim() : '';

                    editingRow = row;
                    editingLabelEl = nameEl || labelEl;
                    if (editInputEl) editInputEl.value = current;

                    if (editModal) {
                        editModal.show();
                        setTimeout(function () { try { editInputEl && editInputEl.focus(); } catch (e) {} }, 150);
                    } else {
                        // Fallback (if bootstrap missing) - keep behavior functional
                        var newVal = prompt('Edit display name', current);
                        if (newVal !== null) {
                            var trimmed = newVal.trim();
                            if (trimmed && trimmed !== current) {
                                // mimic modal save behavior
                                if (row.getAttribute('data-local-only') === '1') {
                                    if (labelEl) labelEl.textContent = trimmed;
                                } else {
                                    var userId = row.getAttribute('data-user-id');
                                    fetch('/admin/users/' + userId + '/name', {
                                        method: 'PUT',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({ name: trimmed })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            if (labelEl) labelEl.textContent = trimmed;
                                        } else {
                                            alert('Failed to update display name');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Error updating display name');
                                    });
                                }
                            }
                        }
                    }
                    return;
                }

                if (target.classList.contains('user-account-item-block')) {
                    var row = target.closest('.user-account-item');
                    if (!row) return;
                    var userId = row.getAttribute('data-user-id');
                    var blocked = !row.classList.contains('blocked');

                    if (row.getAttribute('data-local-only') === '1') {
                        row.classList.toggle('blocked');
                        row.setAttribute('data-blocked', blocked ? 'true' : 'false');
                        target.textContent = blocked ? 'Unblock' : 'Block';
                        target.classList.remove('btn-warning', 'btn-success');
                        target.classList.add(blocked ? 'btn-success' : 'btn-warning');
                        return;
                    }

                    fetch('/admin/users/' + userId + '/status', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ status: !blocked })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            row.classList.toggle('blocked');
                            row.setAttribute('data-blocked', blocked ? 'true' : 'false');
                            target.textContent = blocked ? 'Unblock' : 'Block';
                            target.classList.remove('btn-warning', 'btn-success');
                            target.classList.add(blocked ? 'btn-success' : 'btn-warning');
                        } else {
                            alert('Failed to update user status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating user status');
                    });
                    return;
                }

                if (target.classList.contains('user-account-item-remove')) {
                    var row = target.closest('.user-account-item');
                    if (!row) return;
                    var userId = row.getAttribute('data-user-id');

                    if (row.getAttribute('data-local-only') === '1') {
                        row.remove();
                        return;
                    }

                    disablingRow = row;
                    if (disableModal) {
                        disableModal.show();
                    } else {
                        // Fallback if bootstrap missing
                        if (confirm('Are you sure you want to disable this user?')) {
                            fetch('/admin/users/' + userId, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    row.remove();
                                } else {
                                    alert('Failed to disable user');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error disabling user');
                            });
                        }
                    }
                    return;
                }
            });
        }

        if (disableConfirmBtn) {
            disableConfirmBtn.addEventListener('click', function () {
                var row = disablingRow;
                disablingRow = null;
                if (!row) {
                    if (disableModal) disableModal.hide();
                    return;
                }

                var userId = row.getAttribute('data-user-id');
                disableConfirmBtn.disabled = true;

                fetch('/admin/users/' + userId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                        if (disableModal) disableModal.hide();
                    } else {
                        alert('Failed to disable user');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error disabling user');
                })
                .finally(function () {
                    disableConfirmBtn.disabled = false;
                });
            });
        }
        if (editSaveBtn && editInputEl && list) {
            editSaveBtn.addEventListener('click', function () {
                if (!editingRow || !editingLabelEl) return;
                var current = editingLabelEl.textContent.trim();
                var nextVal = String(editInputEl.value || '').trim();

                if (!nextVal || nextVal === current) {
                    if (editModal) editModal.hide();
                    return;
                }

                if (editingRow.getAttribute('data-local-only') === '1') {
                    editingLabelEl.textContent = nextVal;
                    if (editModal) editModal.hide();
                    return;
                }

                var userId = editingRow.getAttribute('data-user-id');
                fetch('/admin/users/' + userId + '/name', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ name: nextVal })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (editingLabelEl) editingLabelEl.textContent = nextVal;
                        if (editModal) editModal.hide();
                    } else {
                        alert('Failed to update display name');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating display name');
                });
            });
        }
    });
</script>
@if($errors->any())
<script nonce="{{ $cspNonce ?? '' }}">
    var errors = @json($errors->all());
    alert(errors.join('\n'));
</script>
@endif  
<!-- Custom Modal for Validation Errors -->
<div id="validationModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <p id="modalMessage">Value must be greater than or equal to 0.</p>
        <button id="modalOkBtn" class="btn btn-primary">OK</button>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function() {
        var billRangeForm = document.querySelector('.bill_range-config form');
        var modal = document.getElementById('validationModal');
        var modalMessage = document.getElementById('modalMessage');
        var closeBtn = document.querySelector('.close');
        var okBtn = document.getElementById('modalOkBtn');
        
        function showModal(message) {
            modalMessage.textContent = message;
            modal.style.display = 'block';
        }
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        closeBtn.onclick = closeModal;
        okBtn.onclick = closeModal;
        
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        
        if (billRangeForm) {
            billRangeForm.addEventListener('submit', function(e) {
                var upperRange = document.getElementById('upper_range').value;
                var lowerRange = document.getElementById('lower_range').value;
                
                if (parseFloat(upperRange) < 0 || parseFloat(lowerRange) < 0) {
                    e.preventDefault();
                    showModal('Error: Bill range values cannot be negative. Please enter positive values only.');
                    return false;
                }
            });
        }
    });
</script>