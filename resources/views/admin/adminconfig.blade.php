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
                <div class="admin-config-left">
                    <h1 class="admin_config-title">Configurations</h1>
                    <div class="admin-config-btn-col">
                        <button type="button" class="admin-config-btn is-active" data-config-target="latest-bill-range">Latest Bill Range</button>
                        <button type="button" class="admin-config-btn" data-config-target="bill-arears-quota">Bill Arears Quota</button>
                        <button type="button" class="admin-config-btn" data-config-target="user-account">User Account</button>
                    </div>
                </div>

                <div class="admin-config-right">
                    <form action="{{ route('configurations.billrange') }}" method="POST">
                            @csrf
                            @method('post')
                        <div class="admin-config-form is-active" data-config-block="latest-bill-range">
                            <p class="admin-config-hint">Here you can change the current values</p>
                            
                            <div class="admin-config-field">
                                <label for="name">Upper Range :</label>
                                <input type="number" name="upper_range" id="upper_range" placeholder="Current value is 4000" required />
                            </div>

                            <div class="admin-config-field">
                                <label for="lowername">Lower Range :</label>
                                <input type="number" name="lower_range" id="lower_range" placeholder="Current value is 2000" required />
                            </div>

                            <button type="submit" class="config-btn-range">Save</button>
                            
                        </div>
                    </form>

                    <div class="admin-config-form" data-config-block="bill-arears-quota">
                        <p class="admin-config-hint">Here you can change the Bill Areas Quota</p>
                        <div class="admin-config-field">
                            <label for="call-centre-staff">Call Centre Staff :</label>
                            <input type="text" id="call-centre-staff" />
                        </div>

                        <div class="admin-config-field">
                            <label for="call-centre" class="admin-config-label-two-line">Call<br />Centre :</label>
                            <input type="text" id="call-centre" />
                        </div>

                        <div class="admin-config-field">
                            <label for="staff">Staff :</label>
                            <input type="text" id="staff" />
                        </div>

                        <button type="button" class="config-btn-range">Save</button>
                    </div>

                    <div class="admin-config-form" data-config-block="user-account">
                        <p class="admin-config-hint">Here you can change the user account</p>

                        <div class="user-account-panel">
                            <div class="user-account-add">
                                <input type="text" id="user-account-input" placeholder="Enter name or id" />
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
@endsection  

<style>
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
        border: none;
        background: #00b4eb;
        color: white;
        border-radius: 50px;
        cursor: pointer;
        transition: 0.2s ease;
        font-weight: 500;
        text-align: left;
    }

    .admin-config-btn-col .admin-config-btn:hover {
        background-color: var(--btn-primary-hover-bg);
        color: var(--btn-light-bg);
    }

    .admin-config-btn.is-active {
        background: #11760a;
        color: white;
        box-shadow: 0 12px 26px rgba(17, 118, 10, 0.35);
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
        width: min(420px, 100%);
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
    }

    @media (max-width: 480px) {
        .admin-config-btn-col {
            grid-template-columns: 1fr;
        }

        .config-btn-range {
            padding: 10px;
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