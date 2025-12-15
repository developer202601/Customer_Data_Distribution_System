@extends('layouts.admin')

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
                    <form action="{{ route('configurations.billrange') }}" method="POST" class="card shadow-sm border-0">
                            @csrf
                            @method('post')
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
                                    <input type="number" name="upper_range" id="upper_range" value="{{ $configs['upper_range']->value ?? '' }}" placeholder="Current value" required />
                                </div>

                                <div class="admin-config-field">
                                    <label class="bill-lower" for="lower_range">Lower Range :</label>
                                    <input type="number" name="lower_range" id="lower_range" value="{{ $configs['lower_range']->value ?? '' }}" placeholder="Current value" required />
                                </div>


                                <button type="submit" class="btn btn-primary px-4">Save</button>
                            </div>
                        </div>
                    </form>

                    <form action="{{ route('configurations.billarears') }}" method="POST" class="card shadow-sm border-0">
                        @csrf
                        @method('post')
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
                                    <input type="text" name="ccs" id="call-centre-staff" value="{{ $configs['ccs']->value ?? '' }}" placeholder="Enter Call Centre Staff" />
                                </div>

                                <div class="admin-config-field admin_config_staff">
                                    <label for="call-centre" class="config-bill-areas">Call Centre :</label>
                                    <input type="text" name="cc" id="call-centre" value="{{ $configs['cc']->value ?? '' }}" placeholder="Enter Call Centre" />
                                </div>

                                <div class="admin-config-field  admin_config_staff">
                                    <label for="staff" class="config-bill-areas">Staff :</label>
                                    <input type="text" name="s" id="staff" value="{{ $configs['s']->value ?? '' }}" placeholder="Enter Staff" />
                                </div>

                                <button type="submit" class="btn btn-primary px-4">Save</button>
                            </div>
                        </div>
                    </form>

                    <div class="admin-config-form" data-config-block="user-account">
                        <div class="config-card">
                            <p class="admin-config-hint">Here you can change the user account</p>

                            <div class="user-account-panel">
                                <div class="user-account-add">
                                    <input type="text" id="user-account-input" class="user-acc-input" placeholder="Enter name or id" />
                                    <button type="button" class="user-account-add-btn">Add</button>
                                </div>

                                <div class="user-account-list" aria-live="polite">
                                    <div class="user-account-item" data-user-id="1" data-blocked="false">
                                        <div class="user-account-item-label">User 1</div>
                                        <div class="user-account-item-controls">
                                            <button type="button" class="user-account-item-edit">Edit</button>
                                            <button type="button" class="user-account-item-block">Block</button>
                                            <button type="button" class="user-account-item-remove" aria-label="Remove">×</button>
                                        </div>
                                    </div>

                                    <div class="user-account-item" data-user-id="2" data-blocked="false">
                                        <div class="user-account-item-label">User 2</div>
                                        <div class="user-account-item-controls">
                                            <button type="button" class="user-account-item-edit">Edit</button>
                                            <button type="button" class="user-account-item-block">Block</button>
                                            <button type="button" class="user-account-item-remove" aria-label="Remove">×</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="user-account-actions">
                                    <div class="user-account-save-status" aria-live="polite"></div>
                                    <button type="button" class="user-account-save-btn">Save</button>
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
@endsection  

<style>
    :root {
        --config-border: rgba(0, 0, 0, 0.08);
    }

    .config-card {
        border: 1px solid var(--config-border);
        border-radius: 12px;
        padding: 18px 20px;
        background: transparent;
    }

    .config-card h5 {
        font-weight: 600;
    }

    .user-account-item-controls .user-account-item-edit{
        background: #00b4eb;   
    }

    .user-account-item-controls .user-account-item-edit:hover{
        background: #33c9ff;   
    }

    .user-account-item-controls .user-account-item-block{
        background: red;    
    }

    .user-account-item-controls .user-account-item-block:hover{
        background: #ff4c4c;

    }

    .user-account-add button{
        background: #11760a;
    }

    .user-account-add button:hover{
        background: #15940c;
    }


    .config-bill-areas{
        font-family: Arial, Helvetica, sans-serif;
    }

    .bill-upper, .bill-lower{  
        font-family: Arial, Helvetica, sans-serif;
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
        border: 1px solid var(--bs-border-color, #dee2e6);
        background: var(--bs-body-bg, #fff);
        color: var(--bs-body-color, #212529);
        border-radius: 12px;
        cursor: pointer;
        transition: 0.2s ease;
        font-weight: 600;
        text-align: left;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }

    .admin-config-btn-col .admin-config-btn:hover {
        background-color: var(--bs-primary-bg-subtle, #e7f1ff);
        color: var(--bs-primary, #0d6efd);
        border-color: var(--bs-primary, #0d6efd);
    }

    .admin-config-btn.is-active {
        background: var(--bs-primary, #0d6efd);
        color: #fff;
        border-color: var(--bs-primary, #0d6efd);
        box-shadow: 0 10px 28px rgba(13, 110, 253, 0.25);
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
        background: rgba(17, 118, 10, 0.08);
        border-color: #11760a;
        box-shadow: 0 18px 32px rgba(17, 118, 10, 0.18);
    }

    .admin-config-visual-panel {
        background-color: transparent;
        color: var(--text-primary);
        border-right: 1px solid var(--surface-border);
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
        border: 1px solid #ccc;
        border-radius: 10px;
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
        color: #11760a;
        font-size: 15px;
        font-weight: 700;
        text-align: center;
        background: rgba(17, 118, 10, 0.04);
        padding: 10px 14px;
        border-radius: 8px;
        border: 1px solid rgba(17, 118, 10, 0.08);
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
        border: 1px solid #ccc;
        border-radius: 6px;
        min-width: 220px;
    }

    .user-account-add-btn {
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid var(--surface-border);
        background: white;
        cursor: pointer;
    }

    .user-account-list {
        border: 1px solid var(--surface-border);
        padding: 12px;
        border-radius: 6px;
        min-height: 72px;
        background: rgba(0, 0, 0, 0.02);
    }

    .user-account-item {
        background: rgba(0, 0, 0, 0.03);
        padding: 10px 12px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 6px;
    }

    .user-account-item-controls {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .user-account-item-edit,
    .user-account-item-block {
        padding: 6px 10px;
        border-radius: 6px;
        border: 1px solid var(--surface-border);
        background: white;
        cursor: pointer;
        font-size: 13px;
    }

    .user-account-item.blocked {
        opacity: 0.6;
        background: rgba(0, 0, 0, 0.02);
        text-decoration: line-through;
    }

    .user-account-item:last-child {
        margin-bottom: 0;
    }

    .user-account-item-remove {
        background: transparent;
        border: none;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        color: #333;
    }

    .user-account-save-btn {
        padding: 8px 14px;
        border-radius: 6px;
        border: 1px solid #11760a;
        background: #11760a;
        color: white;
        cursor: pointer;
        font-weight: 600;
    }

    .user-account-save-btn:disabled {
        opacity: 0.6;
        cursor: default;
    }

    /* Center save area in user account panel */
    .user-account-actions {
        margin-top: 12px;
        display: flex;
        justify-content: center;
        gap: 12px;
        align-items: center;
    }

    .user-account-save-status {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .config-btn-range {
        padding: 10px 40px;
        background: #11760a;
        color: white;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        font-weight: bold;
        align-self: center;
        
    }

    .config-btn-range:hover {
        background: #15940c;
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
</style>



<script>
    document.addEventListener('DOMContentLoaded', function() {
        var buttons = document.querySelectorAll('.admin-config-btn[data-config-target]');
        var blocks = document.querySelectorAll('.admin-config-form[data-config-block]');

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
        }

        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                activate(button.getAttribute('data-config-target'));
            });
        });

        var initialButton = document.querySelector('.admin-config-btn.is-active');
        if (initialButton) {
            activate(initialButton.getAttribute('data-config-target'));
        } else if (buttons.length) {
            activate(buttons[0].getAttribute('data-config-target'));
        }
    });
</script>  
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var addBtn = document.querySelector('.user-account-add-btn');
        var input = document.getElementById('user-account-input');
        var list = document.querySelector('.user-account-list');

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        if (addBtn && input && list) {
            function createItem(label, id) {
                var item = document.createElement('div');
                item.className = 'user-account-item';
                if (id) item.setAttribute('data-user-id', id);
                item.setAttribute('data-blocked', 'false');
                item.innerHTML = '<div class="user-account-item-label">' + escapeHtml(label) + '</div>' +
                    '<div class="user-account-item-controls">' +
                    '<button type="button" class="user-account-item-edit">Edit</button>' +
                    '<button type="button" class="user-account-item-block">Block</button>' +
                    '<button type="button" class="user-account-item-remove" aria-label="Remove">×</button>' +
                    '</div>';
                return item;
            }

            addBtn.addEventListener('click', function() {
                var val = input.value.trim();
                if (!val) return;
                var id = Date.now();
                var item = createItem(val, id);
                list.appendChild(item);
                input.value = '';
                input.focus();
            });

            list.addEventListener('click', function(e) {
                var target = e.target;
                if (target.classList.contains('user-account-item-remove')) {
                    var row = target.closest('.user-account-item');
                    if (row) row.remove();
                    return;
                }

                if (target.classList.contains('user-account-item-edit')) {
                    var row = target.closest('.user-account-item');
                    if (!row) return;
                    var labelEl = row.querySelector('.user-account-item-label');
                    var current = labelEl ? labelEl.textContent.trim() : '';
                    var newVal = prompt('Edit user', current);
                    if (newVal !== null) {
                        labelEl.textContent = newVal.trim() || current;
                    }
                    return;
                }

                if (target.classList.contains('user-account-item-block')) {
                    var row = target.closest('.user-account-item');
                    if (!row) return;
                    var blocked = row.classList.toggle('blocked');
                    row.setAttribute('data-blocked', blocked ? 'true' : 'false');
                    target.textContent = blocked ? 'Unblock' : 'Block';
                    return;
                }
            });
        }
        // Save button handler: collect current list and show saved status (client-side)
        var saveBtn = document.querySelector('.user-account-save-btn');
        var saveStatus = document.querySelector('.user-account-save-status');
        if (saveBtn && list) {
            saveBtn.addEventListener('click', function() {
                var rows = list.querySelectorAll('.user-account-item');
                var data = [];
                rows.forEach(function(r) {
                    var label = r.querySelector('.user-account-item-label')?.textContent.trim() || '';
                    var id = r.getAttribute('data-user-id') || null;
                    var blocked = r.getAttribute('data-blocked') === 'true' || r.classList.contains('blocked');
                    data.push({
                        id: id,
                        label: label,
                        blocked: blocked
                    });
                });

                // simple UI feedback
                saveBtn.disabled = true;
                if (saveStatus) saveStatus.textContent = 'Saving...';

                // simulate save delay
                setTimeout(function() {
                    saveBtn.disabled = false;
                    if (saveStatus) saveStatus.textContent = 'Saved ' + data.length + ' account(s)';
                    console.log('User accounts saved (client-side):', data);
                    setTimeout(function() {
                        if (saveStatus) saveStatus.textContent = '';
                    }, 2500);
                }, 600);
            });
        }
    });
</script>  