@extends('layouts.cc')

@section('title', 'Manage Assignments')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary ms-2">Logout</button>
</form>
@endsection

@push('styles')
<style nonce="{{ $cspNonce ?? '' }}">
    .cc-fallback-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1070;
    }

    .cc-fallback-modal {
        position: fixed !important;
        inset: 50% auto auto 50% !important;
        transform: translate(-50%, -50%) !important;
        z-index: 1080 !important;
        max-width: 900px !important;
        width: calc(100% - 40px) !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        border-radius: 0.75rem;
        background: #fff;
    }
    /* disabled form wrapper visual */
    #ccCallFormWrapperAssign.cc-disabled {
        opacity: 0.6;
        pointer-events: none;
    }
    .cc-assigned-grid .card {
        border: 1px solid rgba(13, 110, 253, 0.08);
    }
    .cc-assignment-card .list-group-item {
        border-radius: 0.65rem;
        margin-bottom: 0.35rem;
        background: rgba(248, 249, 252, 0.6);
    }
    .cc-assignment-card .list-group-item:hover {
        background: rgba(13, 110, 253, 0.08);
    }
    .cc-interactions-scroll {
        max-height: 360px;
        overflow-y: auto;
        border: 1px solid rgba(0,0,0,0.04);
        border-radius: 0.375rem;
        background: #fff;
    }
    .cc-interactions-table td { vertical-align: top; padding: .5rem .75rem; }
    .cc-interactions-table tbody tr + tr td { border-top: 1px solid rgba(0,0,0,0.04); }
    .cc-details-tabs { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
    /* Make the left details container flex so interactions panel can expand */
    .cc-details-panel { min-height: 320px; overflow: hidden; display: flex; flex-direction: column; }
    /* Details stays its natural height, interactions take remaining space and scroll */
    #ccDetailsPanel { flex: none; }
    #ccInteractionsPanel { flex: 1 1 auto; overflow: hidden; }
    #ccInteractionsPanel .cc-interactions-scroll { height: 100%; }
    /* Ensure modal never extends underneath fixed nav and remains scrollable */
    :root { --cc-navbar-offset: 90px; }
    .modal-dialog { max-height: calc(100vh - var(--cc-navbar-offset)); }
    .modal-content { max-height: calc(100vh - var(--cc-navbar-offset)); overflow: hidden; }
    .modal-body { overflow-y: auto; }

    /* When bootstrap centers modals with translateY(-50%), they can sit under a fixed navbar.
       Override centering so modals are placed below the navbar instead. */
    .modal-dialog.modal-dialog-centered {
        transform: none !important;
        margin: calc(var(--cc-navbar-offset) / 2) auto 1.5rem auto;
    }

    /* Fallback modal (used when JS bootstrap fails): position below navbar and keep horizontally centered */
    .cc-fallback-modal {
        position: fixed !important;
        top: calc(var(--cc-navbar-offset) + 10px) !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        z-index: 1080 !important;
        max-width: 900px !important;
        width: calc(100% - 40px) !important;
        max-height: calc(100vh - calc(var(--cc-navbar-offset) + 40px));
        overflow: hidden;
    }
</style>
@endpush

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card shadow-sm" style="border-radius: 1rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Regional Billing</p>
                        <h1 class="h4 mb-0">Assigned Rows</h1>
                    </div>
                    
                </div>

                {{-- flash status: show as popup toast only, do not render inline alert --}}
                @if(session('status'))
                    <div aria-live="polite" aria-atomic="true" class="position-fixed top-0 end-0 p-3" style="z-index: 1100;">
                        <div id="cc-status-toast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">{{ session('status') }}</div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>
                    <script nonce="{{ $cspNonce ?? '' }}">
                        document.addEventListener('DOMContentLoaded', function () {
                            try {
                                var t = document.getElementById('cc-status-toast');
                                if (t && window.bootstrap && typeof bootstrap.Toast === 'function') {
                                    var toast = new bootstrap.Toast(t, { delay: 4000 });
                                    toast.show();
                                } else if (t) {
                                    // fallback
                                    alert(t.querySelector('.toast-body')?.textContent || 'Status');
                                }
                            } catch (e) { console.error(e); }
                        });
                    </script>
                @endif

                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Accepted assignments:</strong> <span id="ccTotalAssigned">{{ number_format($assignments->count()) }}</span>
                    </div>
                    <div class="text-muted small">{{ $reportLabel ?? 'Current report' }}</div>
                </div>

                @if(($latestReportPending ?? 0) > 0)
                    <div class="mb-4">
                        <div class="card border-0 rounded-4 bg-white shadow-sm">
                            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase small text-muted mb-1">New report assigned</p>
                                    <h3 class="h6 mb-2">
                                        Accept or reject the {{ number_format($latestReportPending) }} pending rows from {{ $latestReportLabel ?? ('Report #'.$latestReportId) }} before you start calling.
                                    </h3>
                                    <p class="small text-muted mb-1">If you already accepted rows from an earlier report, those assignments are now read-only and will be replaced with this new batch once you accept it.</p>
                                    @if(!empty($latestReportAllReassigned))
                                        <p class="small text-muted mb-0">These look like reissued rows. If you reject them, we'll discard your copy and reopen the original for the previous owner. No reason is required.</p>
                                    @else
                                        <p class="small text-muted mb-0">Rejections update only the assignment table (no interaction record is created) and a short reason is required.</p>
                                    @endif
                                </div>
                                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                                    <form method="post" action="{{ route('rb.assignments.acceptAll', $currentUserId ?? auth()->id()) }}?report={{ $latestReportId }}" id="ccAcceptNewForm">
                                        @csrf
                                        <input type="hidden" name="report" value="{{ $latestReportId }}">
                                        <button type="submit" class="btn btn-success btn-sm px-4">Accept rows</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm px-4" data-bs-toggle="modal" data-bs-target="#ccRejectReasonModal">
                                        Reject rows
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="ccToggleOldRows">Show previous report rows</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="ccRejectReasonModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <form method="post" action="{{ route('rb.assignments.rejectAll', $currentUserId ?? auth()->id()) }}?report={{ $latestReportId }}" id="ccRejectNewForm" class="modal-content">
                                @csrf
                                <input type="hidden" name="report" value="{{ $latestReportId }}">
                                <input type="hidden" name="requires_reason" value="{{ $latestReportAllReassigned ? '0' : '1' }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reject pending rows</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    @if(!empty($latestReportAllReassigned))
                                        <p class="small text-muted mb-0">These are reassigned copies. Rejecting will remove your copy and return the original to pending for the previous assignee. No reason needed.</p>
                                        <input type="hidden" name="rejection_note" value="">
                                    @else
                                        <p class="small text-muted mb-3">Rejected rows will stay in the assignment table only; no interaction record is created. Please explain why you are declining these rows.</p>
                                        <div class="mb-3">
                                            <label for="ccRejectReason" class="form-label small">Rejection reason</label>
                                            <textarea name="rejection_note" id="ccRejectReason" class="form-control form-control-sm" rows="3" required></textarea>
                                        </div>
                                    @endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger btn-sm">Reject rows</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="row g-4 mt-3 cc-assigned-grid">
                    @php $singleGroup = $grouped->count() <= 1; @endphp
                    @forelse($grouped as $userId => $items)
                        @php
                            $first = $items->first();
                            $agent = $first->agent;
                            $count = $items->count();
                            $acceptedCount = $items->where('accepted', true)->count();
                            $pendingCount = $count - $acceptedCount;
                            $accepted = $items->where('accepted', true);
                        @endphp
                        <div class="{{ $singleGroup ? 'col-12' : 'col-12 col-lg-6' }}">
                            <div class="card border-0 rounded-4 bg-white shadow-sm h-100 cc-assignment-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h3>My Assignments</h3>
                                            
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success">{{ $acceptedCount }} rows</span>
                                        </div>
                                    </div>
                                    {{-- Only showing accepted rows from current report --}}

                                    {{-- Accepted rows (always shown below pending) --}}
                                    @if(true)
                                        <div class="mt-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                                <p class="small text-uppercase text-muted mb-0">Accepted rows (click to view)</p>
                                                <div class="btn-group btn-group-sm" role="group" aria-label="Assignment filters">
                                                    <button type="button" class="btn btn-outline-secondary" data-cc-filter="all">All (0)</button>
                                                    <button type="button" class="btn btn-outline-secondary" data-cc-filter="called">Called (0)</button>
                                                    <button type="button" class="btn btn-outline-secondary active" data-cc-filter="uncalled">Not called (0)</button>
                                                    <button type="button" class="btn btn-outline-secondary" data-cc-filter="authorized">User Not Authorized (0)</button>
                                                    <button type="button" class="btn btn-outline-secondary" data-cc-filter="promise_overdue">Promise overdue (0)</button>
                                                    <button type="button" class="btn btn-outline-secondary" data-cc-filter="number_invalid">Number invalid (0)</button>
                                                    <button type="button" class="btn btn-outline-secondary" data-cc-filter="not_answered">Not answered (0)</button>
                                                    <button type="button" class="btn btn-outline-secondary" data-cc-filter="not_relevant_person">Not relevant person (0)</button>
                                                </div>
                                            </div>
                                            <div class="list-group list-group-flush" id="ccAssignmentList" data-user-id="{{ $userId }}" data-report-id="{{ $reportId }}" data-latest-report-id="{{ $latestReportId ?? '' }}" data-latest-report-pending="{{ $latestReportPending ?? 0 }}">
                                                <div class="list-group-item text-muted small">Loading accepted rows...</div>
                                            </div>
                                            <div class="d-flex justify-content-center mt-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm d-none" id="ccLoadMoreBtn">Load more</button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="card border-0 rounded-4 bg-white shadow-sm">
                                <div class="card-body text-muted small text-center">No assigned rows.</div>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="ccAssignmentRowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content card shadow-sm">
            <div class="modal-header">
                <h5 class="modal-title">Assignment details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row gy-4">
                    <div class="col-lg-6">
                        <div class="cc-details-tabs mb-2" role="tablist">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-cc-details-tab="details">Details</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-cc-details-tab="interactions">Interactions</button>
                        </div>
                        <div id="ccAssignmentDetailFields" class="cc-details-panel border rounded-3 p-3 bg-light small text-muted">
                            <div id="ccDetailsPanel">Select an accepted row to view its contact details.</div>
                            <div id="ccInteractionsPanel" style="display:none"></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div id="ccSelectedName" class="fw-semibold">&nbsp;</div>
                                <div id="ccSelectedAmounts" class="small text-muted">&nbsp;</div>
                            </div>
                            <div class="text-end">
                                <button type="button" id="ccStartCallBtn" class="btn btn-sm btn-outline-primary">Start call</button>
                                <div id="ccStartCallNote" class="small text-danger mt-2" style="display:none">Call not allowed for this row.</div>
                            </div>
                        </div>
                        <div id="ccCallFormWrapperAssign" class="cc-disabled card-body bg-white rounded-3">
                        <form id="ccAssignmentCallForm" class="row g-3" data-loader-off="1">
                            <input type="hidden" name="assignment_id" id="ccCallAssignmentId">
                            <div class="col-12">
                                <label class="form-label small">Outcome</label>
                                <select name="outcome" id="ccCallOutcome" class="form-select form-select-sm" disabled>
                                    <option value="" disabled selected>Select outcome category</option>
                                    <option value="promise_to_pay">Promise to pay</option>
                                    <option value="temporary_financial_difficulty">Temporary financial difficulty</option>
                                    <option value="billing_dispute">Billing / Dispute issues</option>
                                    <option value="service_quality">Service quality issues</option>
                                    <option value="intention_to_disconnect">Intention to disconnect</option>
                                    <option value="contact_issue">Contact issue</option>
                                    <option value="payment_behavior">Payment behavior pattern</option>
                                    <option value="escalation_risk">Dispute / Escalation risk</option>
                                </select>
                            </div>
                            <div class="col-12" id="ccDependentOutcomeWrap" style="display:none;">
                                <label class="form-label small">Outcome details</label>
                                <div id="ccDependentOutcomeContainer"></div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ccNotRelevantPerson" name="not_relevant_person" value="1" disabled>
                                    <label class="form-check-label small" for="ccNotRelevantPerson">
                                        Not the relevant person contacted
                                    </label>
                                </div>
                            </div>
                            <div class="col-12" id="ccPaymentExpectedWrap" style="display:none;">
                                <label class="form-label small">Payment expected at</label>
                                <input type="date" name="payment_expected_at" id="ccPaymentExpected" class="form-control form-control-sm" disabled>
                            </div>
                            <div class="col-12" id="ccPaymentDateWrap" style="display:none;">
                                <label class="form-label small">Payment date</label>
                                <input type="date" name="payment_date" id="ccPaymentDate" class="form-control form-control-sm" disabled>
                            </div>
                            <div class="col-12" id="ccPaidAmountWrap" style="display:none;">
                                <label class="form-label small">Paid amount</label>
                                <input type="number" step="0.01" name="paid_amount" id="ccPaidAmount" class="form-control form-control-sm" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Note (optional)</label>
                                <textarea name="note" id="ccCallNote" class="form-control form-control-sm" rows="3" disabled></textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" id="ccSaveCallBtn" class="btn btn-primary btn-sm d-none" disabled>Save call</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                            </div>
                            <div class="col-12">
                                <div id="ccCallStatus" class="small text-muted"></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                <script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const assignmentRowModal = document.getElementById('ccAssignmentRowModal');
    const detailFields = document.getElementById('ccAssignmentDetailFields');
    const detailPanel = document.getElementById('ccDetailsPanel');
    const interactionsPanel = document.getElementById('ccInteractionsPanel');
    const detailTabs = document.querySelectorAll('[data-cc-details-tab]');
    const callForm = document.getElementById('ccAssignmentCallForm');
    const outcomeEl = document.getElementById('ccCallOutcome');
    const paymentWrap = document.getElementById('ccPaymentExpectedWrap');
    const paymentDateWrap = document.getElementById('ccPaymentDateWrap');
    const paidAmountWrap = document.getElementById('ccPaidAmountWrap');
    const notRelevantPersonEl = document.getElementById('ccNotRelevantPerson');
    const paymentInput = document.getElementById('ccPaymentExpected');
    const paymentDate = document.getElementById('ccPaymentDate');
    const paidAmount = document.getElementById('ccPaidAmount');
    const statusField = document.getElementById('ccCallStatus');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const NOT_RELEVANT_PERSON_OUTCOME = 'not relevant person contacted';
    let bootstrapModal = null;
    if (assignmentRowModal && window.bootstrap) {
        bootstrapModal = new bootstrap.Modal(assignmentRowModal, { keyboard: true });
        assignmentRowModal.style.display = 'none';
    }

    // Dependent dropdown options for each main category
    const outcomeDependentOptions = {
        promise_to_pay: [
            { value: '', label: 'Select...' },
            { value: 'will_pay_today', label: 'Will pay today' },
            { value: 'will_pay_within_3_days', label: 'Will pay within 3 days' },
            { value: 'salary_not_yet_credited', label: 'Salary not yet credited' },
            { value: 'bank_transfer_delay', label: 'Bank transfer delay' }
        ],
        temporary_financial_difficulty: [
            { value: '', label: 'Select...' },
            { value: 'salary_delay', label: 'Salary delay' },
            { value: 'job_loss', label: 'Job loss' },
            { value: 'medical_emergency', label: 'Medical emergency' },
            { value: 'business_slowdown', label: 'Business slowdown' },
            { value: 'seasonal_income_fluctuation', label: 'Seasonal income fluctuation' }
        ],
        billing_dispute: [
            { value: '', label: 'Select...' },
            { value: 'incorrect_bill_amount', label: 'Incorrect bill amount' },
            { value: 'overcharging_complaint', label: 'Overcharging complaint' },
            { value: 'double_billing', label: 'Double billing' },
            { value: 'service_not_working_but_charged', label: 'Service not working but charged' },
            { value: 'plan_mismatch', label: 'Plan mismatch' },
            { value: 'previous_payment_not_updated', label: 'Previous payment not updated' },
            { value: 'installation_issues', label: 'Installation issues' }
        ],
        service_quality: [
            { value: '', label: 'Select...' },
            { value: 'slow_internet', label: 'Slow internet' },
            { value: 'frequent_disconnection', label: 'Frequent disconnection' },
            { value: 'router_issue', label: 'Router issue' },
            { value: 'fiber_damage', label: 'Fiber damage' },
            { value: 'technical_complaint_pending', label: 'Technical complaint pending' },
            { value: 'waiting_for_technician_visit', label: 'Waiting for technician visit' }
        ],
        intention_to_disconnect: [
            { value: '', label: 'Select...' },
            { value: 'wants_to_cancel_service', label: 'Wants to cancel service' },
            { value: 'switching_to_competitor', label: 'Switching to a competitor' },
            { value: 'moving_house', label: 'Moving house' },
            { value: 'no_longer_needed', label: 'No longer needed' },
            { value: 'temporary_relocator', label: 'Temporary relocator' },
            { value: 'business_closed', label: 'Business closed' }
        ],
        contact_issue: [
            { value: '', label: 'Select...' },
            { value: 'no_answer', label: 'No answer' },
            { value: 'switched_off', label: 'Switched off' },
            { value: 'wrong_number', label: 'Wrong number' },
            { value: 'number_unreachable', label: 'Number unreachable' },
            { value: 'refused_to_talk', label: 'Refused to talk' }
        ],
        payment_behavior: [
            { value: '', label: 'Select...' },
            { value: 'always_pays_late', label: 'Always pays late' },
            { value: 'habitual_suspend', label: 'Habitual suspend' },
            { value: 'broken_ptp_multiple_times', label: 'Broken PTP multiple times' },
            { value: 'demands_extensions_monthly', label: 'Demands extensions monthly' }
        ],
        escalation_risk: [
            { value: '', label: 'Select...' },
            { value: 'threatened_complaint_to_regulators', label: 'Threatened complaint to regulators' },
            { value: 'angry_customer', label: 'Angry customer' },
            { value: 'escalation_requested', label: 'Escalation requested' },
            { value: 'legal_notice_mentioned', label: 'Legal notice mentioned' }
        ]
    };

    function renderDependentDropdown(selectedOutcome) {
        const wrap = document.getElementById('ccDependentOutcomeWrap');
        const container = document.getElementById('ccDependentOutcomeContainer');
        if (!wrap || !container) return;
        const key = (selectedOutcome || '').toString();
        const options = outcomeDependentOptions[key];
        if (!options || !options.length) {
            wrap.style.display = 'none';
            container.innerHTML = '';
            return;
        }
        let html = '<select name="outcome_detail" id="ccOutcomeDetail" class="form-select form-select-sm">';
        options.forEach(opt => {
            const val = opt.value || '';
            const lbl = opt.label || opt.value || '';
            html += `<option value="${val}">${lbl}</option>`;
        });
        html += '</select>';
        container.innerHTML = html;
        wrap.style.display = 'block';
        // attach change handler to dependent select to preserve payment fields behavior
        const dep = document.getElementById('ccOutcomeDetail');
        if (dep) {
            dep.addEventListener('change', function () {
                const v = this.value || '';
                // clear previous payment fields
                clearPaymentFields();
                if (v === 'will_pay_today') {
                    // treat as payment date (today) and show paid amount field
                    paymentDateWrap.style.display = 'block';
                    paidAmountWrap.style.display = 'block';
                    const today = new Date().toISOString().slice(0,10);
                    if (paymentDate) paymentDate.value = today;
                } else if (v === 'will_pay_within_3_days' || v === 'will_pay_within_7_days') {
                    paymentWrap.style.display = 'block';
                    const days = v === 'will_pay_within_3_days' ? 3 : 7;
                    const d = new Date(); d.setDate(d.getDate() + days);
                    if (paymentInput) paymentInput.value = d.toISOString().slice(0,10);
                } else if (v === 'salary_not_yet_credited' || v === 'bank_transfer_delay' || v === 'salary_delay') {
                    // expected payment but date unknown
                    paymentWrap.style.display = 'block';
                } else if (v === 'paid') {
                    paymentDateWrap.style.display = 'block';
                    paidAmountWrap.style.display = 'block';
                    const today = new Date().toISOString().slice(0,10);
                    if (paymentDate) paymentDate.value = today;
                } else {
                    // leave payment fields hidden for other suboptions
                }
            });
        }
    }

    window._ccNeedsListRefresh = false;
    function refreshAssignmentList() {
        if (typeof loadAssignments === 'function') {
            paging.page = 1;
            loadAssignments(false);
        }
    }
    try {
        if (assignmentRowModal && window.bootstrap && typeof bootstrap.Modal === 'function') {
            assignmentRowModal.addEventListener('hidden.bs.modal', function () {
                try {
                    if (window._ccNeedsListRefresh) {
                        window._ccNeedsListRefresh = false;
                        refreshAssignmentList();
                    }
                    // also clear call form so selections don't persist between rows
                    try {
                        if (typeof renderDependentDropdown === 'function') renderDependentDropdown('');
                        if (typeof clearPaymentFields === 'function') clearPaymentFields();
                        if (callForm) callForm.reset();
                        if (outcomeEl) outcomeEl.selectedIndex = 0;
                        if (notRelevantPersonEl) { notRelevantPersonEl.checked = false; notRelevantPersonEl.disabled = true; }
                        const saveBtn = document.getElementById('ccSaveCallBtn');
                        if (saveBtn) { saveBtn.disabled = true; saveBtn.classList.add('d-none'); }
                        const wrapper = document.getElementById('ccCallFormWrapperAssign');
                        if (wrapper) wrapper.classList.add('cc-disabled');
                        const startBtn = document.getElementById('ccStartCallBtn');
                        if (startBtn) startBtn.disabled = false;
                    } catch (e) { /* ignore */ }
                } catch (e) { /* ignore */ }
            });
        }
    } catch (e) { /* ignore */ }

    function showModal() {
        if (!assignmentRowModal) return;
        if (bootstrapModal) {
            try {
                assignmentRowModal.style.display = '';
                bootstrapModal.show();
                return;
            } catch (e) {
                console.error('bootstrap modal show failed, falling back', e);
                // fall through to fallback display
            }
        }
        assignmentRowModal.style.display = 'block';
        assignmentRowModal.classList.add('cc-fallback-modal');
        if (!document.getElementById('cc-fallback-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.id = 'cc-fallback-backdrop';
            backdrop.className = 'cc-fallback-backdrop';
            backdrop.addEventListener('click', hideModal);
            document.body.appendChild(backdrop);
        }
    }

    function hideModal() {
        if (!assignmentRowModal) return;
        assignmentRowModal.style.display = 'none';
        assignmentRowModal.classList.remove('cc-fallback-modal');
        const backdrop = document.getElementById('cc-fallback-backdrop');
        if (backdrop) backdrop.remove();
        // If saving an interaction requested a list refresh, do it now
        try {
            if (window._ccNeedsListRefresh) {
                window._ccNeedsListRefresh = false;
                refreshAssignmentList();
            }
        } catch (e) { /* ignore */ }
        // Clear call form and dependent fields so selections don't persist between rows
        try {
            if (typeof renderDependentDropdown === 'function') {
                renderDependentDropdown('');
            }
            if (typeof clearPaymentFields === 'function') {
                clearPaymentFields();
            }
            if (callForm) callForm.reset();
            if (outcomeEl) outcomeEl.selectedIndex = 0;
            if (notRelevantPersonEl) { notRelevantPersonEl.checked = false; notRelevantPersonEl.disabled = true; }
            const saveBtn = document.getElementById('ccSaveCallBtn');
            if (saveBtn) { saveBtn.disabled = true; saveBtn.classList.add('d-none'); }
            const wrapper = document.getElementById('ccCallFormWrapperAssign');
            if (wrapper) wrapper.classList.add('cc-disabled');
            const startBtn = document.getElementById('ccStartCallBtn');
            if (startBtn) startBtn.disabled = false;
        } catch (e) { /* ignore */ }
    }

    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-bs-dismiss="modal"]');
        if (!btn) return;
        const modal = btn.closest('.modal');
        if (modal) hideModal();
    });

    const tabPanels = { details: detailPanel, interactions: interactionsPanel };
    function setDetailsTab(tabName) {
        detailTabs.forEach(btn => {
            const target = btn.dataset.ccDetailsTab;
            const panel = tabPanels[target];
            btn.classList.toggle('active', target === tabName);
            if (panel) {
                panel.style.display = target === tabName ? '' : 'none';
            }
        });
    }
    detailTabs.forEach(btn => {
        btn.addEventListener('click', () => {
            setDetailsTab(btn.dataset.ccDetailsTab || 'details');
        });
    });
    setDetailsTab('details');

    async function fetchDetails(assignmentId) {
        if (!assignmentId) return null;
        const res = await fetch(`/rb/assignments/${assignmentId}/details`, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) return null;
        return res.json();
    }

    function formatPaymentExpectation(outcome) {
        if (!outcome) return null;
        const normalized = outcome.toLowerCase().trim();
        if (normalized.includes('agreed to pay within')) {
            const match = outcome.match(/within\s+(.*)/i);
            if (match && match[1]) {
                return 'Payment within ' + match[1].trim();
            }
        }
        if (normalized === 'not answered') {
            return 'Not answered';
        }
        return outcome.charAt(0).toUpperCase() + outcome.slice(1);
    }

    function formatPaymentResult(interaction) {
        if (!interaction) return null;
        if (interaction.paid) {
            const amount = interaction.paid_amount || '—';
            const date = interaction.payment_date ? ' on ' + interaction.payment_date : '';
            return 'Paid ' + amount + date;
        }
        if (interaction.payment_date) {
            return 'Payment recorded on ' + interaction.payment_date;
        }
        if (interaction.payment_expected_at) {
            return 'Payment expected by ' + interaction.payment_expected_at;
        }
        if ((interaction.outcome || '').toLowerCase().trim() === 'not answered') {
            return 'Not answered';
        }
        return null;
    }

    function renderDetails(data) {
        if (!detailPanel || !interactionsPanel) return;
        const outcome = document.getElementById('ccCallOutcome');
        const note = document.getElementById('ccCallNote');
        const pay = document.getElementById('ccPaymentExpected');
        const saveBtn = document.getElementById('ccSaveCallBtn');
        const startBtn = document.getElementById('ccStartCallBtn');
        const wrapper = document.getElementById('ccCallFormWrapperAssign');
        if (outcome) outcome.disabled = true;
        if (note) note.disabled = true;
        if (notRelevantPersonEl) {
            notRelevantPersonEl.disabled = true;
            notRelevantPersonEl.checked = false;
        }
        if (pay) pay.disabled = true;
        if (paymentDate) paymentDate.disabled = true;
        if (paidAmount) paidAmount.disabled = true;
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('d-none');
        }
        if (startBtn) startBtn.disabled = false;
        if (wrapper) wrapper.classList.add('cc-disabled');

        if (!data) {
            detailPanel.innerHTML = '<div class="text-muted small">Details unavailable.</div>';
            interactionsPanel.innerHTML = '<div class="text-muted small">Details unavailable.</div>';
            document.getElementById('ccSelectedName').textContent = '';
            document.getElementById('ccSelectedAmounts').textContent = '';
            return;
        }
        detailPanel.innerHTML = `
            <div class="mb-2"><strong>Phone:</strong> ${data.phone ?? '—'}</div>
            <div class="mb-2"><strong>Address:</strong> ${data.address ?? '—'}</div>
            <div class="mb-2"><strong>RTO:</strong> ${data.rtom ?? '—'}</div>
            <div class="mb-2"><strong>Customer ref:</strong> ${data.customer_ref ?? '—'}</div>
            <div class="mb-2"><strong>Account #:</strong> ${data.account_num ?? '—'}</div>
            <div class="mb-2"><strong>Sales person:</strong> ${data.sales_person ?? '—'}</div>
            <div class="mb-2"><strong>Sales channel:</strong> ${data.sales_channel ?? '—'}</div>
            <div class="mb-2"><strong>Full address:</strong> ${data.full_address ?? '—'}</div>
        `;

        const paymentsByInteraction = {};
        if (Array.isArray(data.payments)) {
            data.payments.forEach(p => {
                if (p && p.interaction_id) paymentsByInteraction[String(p.interaction_id)] = p;
            });
        }

        if (Array.isArray(data.interactions) && data.interactions.length) {
            const interactionsList = data.interactions;
            let hist = '<div><div class="cc-interactions-scroll"><table class="table table-sm mb-0 cc-interactions-table"><tbody>';
            interactionsList.forEach(i => {
                const agent = i.agent_name ? i.agent_name : ('Agent #'+(i.agent_id||'—'));
                const created = i.created_at ? i.created_at : '';
                const acct = i.account_number ? (' — Acc: ' + i.account_number) : '';
                const noteHtml = i.note ? `<div class="small text-muted mt-1">${i.note}</div>` : '';
                const expectation = formatPaymentExpectation(i.outcome);
                const expectationHtml = expectation ? `<div class="small text-uppercase text-muted mb-1">${expectation}</div>` : '';
                const pay = paymentsByInteraction[String(i.id)];
                if (pay) {
                    const amount = pay.paid_amount ? pay.paid_amount : '—';
                    const date = pay.payment_date ? pay.payment_date : '—';
                    const paidBy = pay.paid_by_agent ? pay.paid_by_agent : '—';
                    const lastContact = pay.last_contact_before_payment ? pay.last_contact_before_payment : null;
                    const lastContactAt = pay.last_contact_before_payment_at ? (' on ' + pay.last_contact_before_payment_at) : '';
                    hist += `<tr class="payment-row"><td></td>`;
                    hist += `<td><div class="small text-uppercase text-muted">PAYMENT</div><div class="fw-semibold text-success">Paid ${amount} on ${date}</div>`;
                    hist += `<div class="small text-muted">Recorded by: ${paidBy}${lastContact ? ' — Last contact before payment: ' + lastContact + (lastContactAt || '') : ''}</div></td></tr>`;
                }
                hist += `<tr class="interaction-row"><td style="width:38%"><div class="fw-semibold">${agent}</div><div class="text-muted small">${created}</div></td>`;
                hist += `<td>${expectationHtml}<div class="text-muted small">${acct}</div>${noteHtml}</td></tr>`;
            });
            hist += '</tbody></table></div></div>';
            interactionsPanel.innerHTML = hist;
        } else {
            interactionsPanel.innerHTML = '<div class="text-muted small">No interactions recorded.</div>';
        }
        const selName = document.getElementById('ccSelectedName');
        const selAmt = document.getElementById('ccSelectedAmounts');
        if (selName) selName.textContent = data.address_name || data.name || '';
        if (selAmt) selAmt.textContent = `Arrears: ${data.arrears ?? '—'} — Bill: ${data.bill ?? '—'}` + (data.call_count ? ` — Calls since assignment: ${data.call_count}` : '');

        // If this row belongs to a previous report, keep interactions read-only and show reason text
        try {
            const currentLatest = assignmentList?.dataset.latestReportId || '';
            const reasonEl = document.getElementById('ccCallStatus');
            const startNoteEl = document.getElementById('ccStartCallNote');

            if (data.report_id && String(data.report_id) !== String(currentLatest)) {
                if (startBtn) startBtn.disabled = true;
                if (saveBtn) { saveBtn.disabled = true; saveBtn.classList.add('d-none'); }
                if (wrapper) wrapper.classList.add('cc-disabled');
                // show a single red status message in the call-status area
                if (reasonEl) {
                    reasonEl.textContent = 'Start call disabled: this row is from an earlier report. Accept or reject newer assignments to enable calls.';
                    reasonEl.classList.remove('text-muted');
                    reasonEl.classList.add('text-danger');
                }
                if (startNoteEl) { startNoteEl.style.display = 'none'; }
            } else {
                if (reasonEl) { reasonEl.textContent = ''; reasonEl.classList.remove('text-danger'); reasonEl.classList.add('text-muted'); }
                if (startNoteEl) { startNoteEl.textContent = ''; startNoteEl.style.display = 'none'; }
                if (startBtn) {
                    startBtn.onclick = () => {
                        if (outcome) outcome.disabled = false;
                        if (note) note.disabled = false;
                        if (notRelevantPersonEl) notRelevantPersonEl.disabled = false;
                        if (pay) pay.disabled = false;
                        if (paymentDate) paymentDate.disabled = false;
                        if (paidAmount) paidAmount.disabled = false;
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.classList.remove('d-none');
                        }
                        if (wrapper) wrapper.classList.remove('cc-disabled');
                        try { renderDependentDropdown(''); } catch (e) { /* ignore */ }
                        startBtn.disabled = true;
                    };
                }
            }
        } catch (e) { /* ignore */ }
    }

    const assignmentList = document.getElementById('ccAssignmentList');
    const loadMoreBtn = document.getElementById('ccLoadMoreBtn');
    const filterButtons = document.querySelectorAll('[data-cc-filter]');
    const toggleOldRowsBtn = document.getElementById('ccToggleOldRows');
    const reportId = assignmentList?.dataset.reportId || '';
    const latestReportId = assignmentList?.dataset.latestReportId || '';
    const userId = assignmentList?.dataset.userId || '';
    let paging = { page: 1, per_page: 50, has_more: false };
    let filterCounts = { all: 0, called: 0, uncalled: 0, authorized: 0, promise_overdue: 0, number_invalid: 0, not_answered: 0, not_relevant_person: 0 };
    // Always show previous-report rows by default so visible items match Accepted count.
    let showOldRows = true;
    let currentFilter = 'uncalled';

    function updateFilterCounts() {
        filterButtons.forEach(btn => {
            const key = btn.dataset.ccFilter;
            const count = filterCounts[key] ?? 0;
            btn.textContent = btn.textContent.replace(/\(.*\)/, `(${count})`);
        });
    }

    function applyFilter(filter) {
        const targetFilter = filter || currentFilter;
        currentFilter = targetFilter;
        document.querySelectorAll('.accepted-row').forEach(row => {
            const called = row.dataset.called === '1';
            const overdue = row.dataset.overdue === '1';
            const outcome = (row.dataset.outcome || '').toLowerCase();
            let show = true;
            if (targetFilter === 'called') show = called;
            else if (targetFilter === 'uncalled') show = !called;
            else if (targetFilter === 'authorized') show = outcome === 'user not authorized';
            else if (targetFilter === 'promise_overdue') show = overdue;
            else if (targetFilter === 'number_invalid') show = outcome === 'number invalid';
            else if (targetFilter === 'not_answered') show = outcome === 'not answered';
            else if (targetFilter === 'not_relevant_person') show = outcome === 'not relevant person contacted';
            row.style.display = show ? '' : 'none';
            enforceOldRowState(row);
        });
    }

    function enforceOldRowState(row) {
        if (!toggleOldRowsBtn) {
            row.style.display = row.style.display === 'none' ? 'none' : '';
            return;
        }
        if (row.dataset.oldRow === '1' && !showOldRows) {
            row.style.display = 'none';
        }
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.ccFilter;
            applyFilter(currentFilter);
        });
    });

    function updateOldRowToggle() {
        if (!toggleOldRowsBtn) return;
        toggleOldRowsBtn.textContent = showOldRows ? 'Hide previous report rows' : 'Show previous report rows';
        toggleOldRowsBtn.classList.toggle('active', showOldRows);
    }

    if (toggleOldRowsBtn) {
        toggleOldRowsBtn.addEventListener('click', () => {
            showOldRows = !showOldRows;
            updateOldRowToggle();
            applyFilter(currentFilter);
        });
        updateOldRowToggle();
    }

    async function loadAssignments(append = false) {
        if (!assignmentList || !userId) return;
        const params = new URLSearchParams({ page: paging.page, per_page: paging.per_page });
        if (reportId) params.append('report', reportId);
        // Always include completed assignments for the accepted rows view
        params.append('include_completed', '1');
        assignmentList.innerHTML = append ? assignmentList.innerHTML : '<div class="list-group-item text-muted small">Loading accepted rows...</div>';
        try {
            const res = await fetch(`/rb/assignments/${userId}/rows?${params.toString()}`, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('Failed to load');
            const data = await res.json();
            const rows = Array.isArray(data.rows) ? data.rows : [];
            if (!append) {
                assignmentList.innerHTML = '';
                filterCounts = { all: 0, called: 0, uncalled: 0, authorized: 0, promise_overdue: 0, number_invalid: 0, not_answered: 0, not_relevant_person: 0 };
            }
            rows.forEach(r => {
                filterCounts.all++;
                if (r.called_in_period) filterCounts.called++; else filterCounts.uncalled++;
                if ((r.latest_outcome || '').toLowerCase() === 'user not authorized') filterCounts.authorized++;
                if (r.promise_overdue) filterCounts.promise_overdue++;
                if ((r.latest_outcome || '').toLowerCase() === 'number invalid') filterCounts.number_invalid++;
                if ((r.latest_outcome || '').toLowerCase() === 'not answered') filterCounts.not_answered++;
                if ((r.latest_outcome || '').toLowerCase() === 'not relevant person contacted') filterCounts.not_relevant_person++;

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action accepted-row';
                btn.dataset.assignmentId = r.assignment_id;
                // include report id to allow client to mark previous-report rows
                if (r.report_id) btn.dataset.reportId = r.report_id;
                if (r.report_id && latestReportId && String(r.report_id) !== String(latestReportId)) {
                    btn.dataset.oldRow = '1';
                    btn.classList.add('text-muted');
                    btn.title = 'Belongs to previous report — read-only';
                }
                btn.dataset.called = r.called_in_period ? '1' : '0';
                btn.dataset.overdue = r.promise_overdue ? '1' : '0';
                btn.dataset.outcome = r.latest_outcome || '';
                btn.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${r.address_name ?? '—'}</strong>
                            <div class="small text-muted">Arrears: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'}</div>
                            <div class="small text-muted">
                                Calls since assignment: ${r.call_count ?? 0}
                                ${r.latest_outcome ? ' • Latest: ' + r.latest_outcome : ''}
                                ${r.latest_payment_expected_at ? ' • Due: ' + r.latest_payment_expected_at : ''}
                                ${r.promise_overdue ? ' • <span class="text-danger">Promise overdue</span>' : ''}
                            </div>
                        </div>
                        <div class="text-muted small text-end">
                            <div>#${r.row_id ?? r.assignment_id}</div>
                            ${Number(r.call_count || 0) > 0 ? '<span class="badge bg-primary-subtle text-primary">Called</span>' : '<span class="badge bg-primary-subtle text-primary">Not yet called</span>'}
                        </div>
                    </div>`;
                btn.addEventListener('click', async () => {
                    document.getElementById('ccCallAssignmentId').value = r.assignment_id;
                    detailPanel.innerHTML = '<div class="text-muted small">Loading...</div>';
                    interactionsPanel.innerHTML = '';
                    try {
                        const details = await fetchDetails(r.assignment_id);
                        if (!details) {
                            detailFields.innerHTML = '<div class="text-danger small">Details unavailable.</div>';
                            return;
                        }
                        renderDetails(details);
                        showModal();
                    } catch (err) {
                        console.error('Failed to load details', err);
                        detailFields.innerHTML = '<div class="text-danger small">Failed to load details. Please try again.</div>';
                    }
                });
                assignmentList.appendChild(btn);
            });

            paging.has_more = data.meta?.has_more ?? false;
            paging.page = (data.meta?.page ?? paging.page) + 1;
            if (loadMoreBtn) {
                loadMoreBtn.classList.toggle('d-none', !paging.has_more);
            }
            updateFilterCounts();
            const active = document.querySelector('[data-cc-filter].active');
            if (active) {
                currentFilter = active.dataset.ccFilter;
            } else {
                const defaultBtn = document.querySelector(`[data-cc-filter="${currentFilter}"]`);
                if (defaultBtn) defaultBtn.classList.add('active');
            }
            updateOldRowToggle();
            applyFilter(currentFilter);
        } catch (e) {
            console.error(e);
            assignmentList.innerHTML = '<div class="list-group-item text-danger small">Failed to load rows.</div>';
            if (loadMoreBtn) loadMoreBtn.classList.add('d-none');
        }
    }

    loadMoreBtn?.addEventListener('click', () => loadAssignments(true));

    // initial fetch
    loadAssignments(false);

    function clearPaymentFields() {
        if (paymentWrap) paymentWrap.style.display = 'none';
        if (paymentDateWrap) paymentDateWrap.style.display = 'none';
        if (paidAmountWrap) paidAmountWrap.style.display = 'none';
        if (paymentInput) paymentInput.value = '';
        if (paymentDate) paymentDate.value = '';
        if (paidAmount) paidAmount.value = '';
    }

    notRelevantPersonEl?.addEventListener('change', function () {
        if (!outcomeEl) return;
        if (this.checked) {
            outcomeEl.value = '';
            outcomeEl.disabled = true;
            clearPaymentFields();
            try { renderDependentDropdown(''); } catch (e) { /* ignore */ }
        } else {
            outcomeEl.disabled = false;
        }
    });

    outcomeEl?.addEventListener('change', function () {
        if (notRelevantPersonEl && notRelevantPersonEl.checked) {
            return;
        }
        const v = this.value;
        try { renderDependentDropdown(v); } catch (e) { /* ignore */ }
        // Show payment date and paid amount immediately when main category selected
        try {
            if (paymentDateWrap) paymentDateWrap.style.display = 'block';
            if (paidAmountWrap) paidAmountWrap.style.display = 'block';
            const today = new Date().toISOString().slice(0,10);
            if (paymentDate) paymentDate.value = today;
            if (paidAmount) paidAmount.value = '';
        } catch (e) { /* ignore */ }
        if (v === 'agreed to pay within 3 days' || v === 'agreed to pay within 7 days') {
            paymentWrap.style.display = 'block';
            paymentDateWrap.style.display = 'none';
            paidAmountWrap.style.display = 'none';
            const days = v.includes('3') ? 3 : 7;
            const d = new Date();
            d.setDate(d.getDate() + days);
            paymentInput.value = d.toISOString().slice(0,10);
            paymentDate.value = '';
            paidAmount.value = '';
        } else if (v === 'paid') {
            paymentWrap.style.display = 'none';
            paymentDateWrap.style.display = 'block';
            paidAmountWrap.style.display = 'block';
            paymentInput.value = '';
            const today = new Date().toISOString().slice(0,10);
            paymentDate.value = today;
            paidAmount.value = '';
        } else {
            paymentWrap.style.display = 'none';
            paymentDateWrap.style.display = 'none';
            paidAmountWrap.style.display = 'none';
            paymentInput.value = '';
            paymentDate.value = '';
            paidAmount.value = '';
        }
    });

    callForm?.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const aid = document.getElementById('ccCallAssignmentId').value;
        if (!aid) {
            statusField && (statusField.textContent = 'Select a row first.');
            return;
        }
        const formData = new FormData(this);
        if (notRelevantPersonEl && notRelevantPersonEl.checked) {
            formData.set('outcome', NOT_RELEVANT_PERSON_OUTCOME);
            formData.delete('payment_expected_at');
            formData.delete('payment_date');
            formData.delete('paid_amount');
            formData.delete('paid');
        }
        if (outcomeEl && outcomeEl.value === 'paid' && !(notRelevantPersonEl && notRelevantPersonEl.checked)) {
            formData.set('paid', '1');
        }
        try {
            const res = await fetch(`/rb/assignments/${aid}/interactions`, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: formData });
            if (!res.ok) throw new Error('failed');
            statusField && (statusField.textContent = 'Saved.');
            // clear the call form inputs so saved values don't "ghost" the form
            try {
                callForm.reset();
                clearPaymentFields();
                if (outcomeEl) outcomeEl.selectedIndex = 0;
                    try { renderDependentDropdown(''); } catch (e) { /* ignore */ }
                if (notRelevantPersonEl) {
                    notRelevantPersonEl.checked = false;
                    notRelevantPersonEl.disabled = true;
                }
                const saveBtn = document.getElementById('ccSaveCallBtn');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.classList.add('d-none');
                }
                const wrapper = document.getElementById('ccCallFormWrapperAssign');
                if (wrapper) wrapper.classList.add('cc-disabled');
                const startBtn = document.getElementById('ccStartCallBtn');
                if (startBtn) startBtn.disabled = false;
            } catch (e) {
                console.error('Error clearing form after save', e);
            }
            // refresh details to include the new interaction
            const updated = await fetchDetails(aid);
            renderDetails(updated);
            // mark that assignment list should refresh once the modal is closed
            try { window._ccNeedsListRefresh = true; } catch (e) { /* ignore */ }
        } catch (err) {
            statusField && (statusField.textContent = 'Save failed.');
        }
    });
});
                </script>
@endsection
