@push('styles')
<style nonce="{{ $cspNonce ?? '' }}">
    .cc-details-panel { min-height: 320px; }
    .cc-interactions-scroll { max-height: 520px; overflow-y: auto; border: 1px solid rgba(0,0,0,.06); border-radius: .375rem; background: #fff; }
    .cc-interactions-table td { vertical-align: top; padding: .5rem .75rem; }
    .cc-interactions-table tbody tr + tr td { border-top: 1px solid rgba(0,0,0,.06); }
    #ccCallFormWrapperAssign.cc-disabled { opacity: .6; pointer-events: none; }
    .theme-dark #assignmentPage .bg-light, .theme-dark #assignmentPage .bg-white { background-color: #1a3a52 !important; color: #fff !important; }
</style>
@endpush

<div class="row gy-4">
    <div class="col-lg-6">
        <div class="d-flex gap-2 mb-2" role="tablist">
            <button type="button" class="btn btn-sm btn-outline-primary active" data-detail-tab="details" aria-selected="true">Details</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-detail-tab="interactions" aria-selected="false">Interactions</button>
        </div>
        <div class="cc-details-panel border rounded-3 p-3 bg-light small" id="ccAssignmentDetailFields">
            <div id="ccDetailsPanel" aria-live="polite">Loading details…</div>
            <div id="ccInteractionsPanel" class="d-none" aria-live="polite"></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="mb-2">
            <div id="ccSelectedName" class="fw-semibold mb-2"></div>
            <div id="ccSelectedAmounts" class="small text-muted"></div>
        </div>
        @unless($interactionEnabled)
            <div class="alert alert-info py-2 small">This assignment is from an earlier report and is read-only.</div>
        @endunless
        <div id="ccCallFormWrapperAssign" class="card-body bg-white rounded-3 {{ $interactionEnabled ? '' : 'cc-disabled' }}">
            <form id="ccAssignmentCallForm" class="row g-3" data-loader-off="1">
                @csrf
                <input type="hidden" name="assignment_id" id="ccCallAssignmentId" value="{{ $assignment->id }}">
                @if($system === 'cc')
                    <div class="col-12">
                        <label class="form-label small" for="ccCallOutcome">Outcome</label>
                        <select name="outcome" id="ccCallOutcome" class="form-select form-select-sm" {{ $interactionEnabled ? '' : 'disabled' }}>
                            <option value="" selected>Select outcome</option>
                            <option value="paid">Paid</option>
                            <option value="number invalid">number invalid</option>
                            <option value="user not authorized">user not authorized</option>
                            <option value="agreed to pay within 3 days">agreed to pay within 3 days</option>
                            <option value="agreed to pay within 7 days">agreed to pay within 7 days</option>
                            <option value="Not answered">Not answered</option>
                        </select>
                    </div>
                @else
                    <div class="col-12">
                        <label class="form-label small" for="ccCallOutcome">Outcome</label>
                        <select name="outcome" id="ccCallOutcome" class="form-select form-select-sm" {{ $interactionEnabled ? '' : 'disabled' }}>
                            <option value="" selected>Select outcome category</option>
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
                    <div class="col-12" id="ccDependentOutcomeWrap" style="display:none">
                        <label class="form-label small" for="ccOutcomeDetail">Outcome details</label>
                        <div id="ccDependentOutcomeContainer"></div>
                    </div>
                @endif
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ccNotRelevantPerson" name="not_relevant_person" value="1" {{ $interactionEnabled ? '' : 'disabled' }}>
                        <label class="form-check-label small" for="ccNotRelevantPerson">Not the relevant person contacted</label>
                    </div>
                </div>
                <div class="col-12" id="ccPaymentExpectedWrap" style="display:none">
                    <label class="form-label small" for="ccPaymentExpected">{{ $system === 'cc' ? 'Payment expected at' : 'Select date' }}</label>
                    <input type="date" name="payment_expected_at" id="ccPaymentExpected" class="form-control form-control-sm" min="{{ now()->toDateString() }}" {{ $interactionEnabled ? '' : 'disabled' }}>
                </div>
                @if($system === 'cc')
                    <div class="col-12" id="ccPaymentDateWrap" style="display:none">
                        <label class="form-label small" for="ccPaymentDate">Payment date</label>
                        <input type="date" name="payment_date" id="ccPaymentDate" class="form-control form-control-sm" {{ $interactionEnabled ? '' : 'disabled' }}>
                    </div>
                    <div class="col-12" id="ccPaidAmountWrap" style="display:none">
                        <label class="form-label small" for="ccPaidAmount">Paid amount (Rs.)</label>
                        <input type="number" step="0.01" name="paid_amount" id="ccPaidAmount" class="form-control form-control-sm" {{ $interactionEnabled ? '' : 'disabled' }}>
                    </div>
                @endif
                <div class="col-12">
                    <label class="form-label small" for="ccCallNote">Note (optional)</label>
                    <textarea name="note" id="ccCallNote" class="form-control form-control-sm" rows="3" {{ $interactionEnabled ? '' : 'disabled' }}></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" id="ccSaveCallBtn" class="btn btn-primary btn-sm" {{ $interactionEnabled ? '' : 'disabled' }}>Save</button>
                </div>
                <div class="col-12"><div id="ccCallStatus" class="small text-muted" role="status" aria-live="polite"></div></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('assignmentPage');
    const system = page.dataset.system;
    const detailPanel = document.getElementById('ccDetailsPanel');
    const interactionsPanel = document.getElementById('ccInteractionsPanel');
    const outcome = document.getElementById('ccCallOutcome');
    const notRelevant = document.getElementById('ccNotRelevantPerson');
    const expectedDate = document.getElementById('ccPaymentExpected');
    const paymentWrap = document.getElementById('ccPaymentExpectedWrap');
    const status = document.getElementById('ccCallStatus');
    const form = document.getElementById('ccAssignmentCallForm');
    const enabled = page.dataset.interactionEnabled === '1';
    const threshold = Number(page.dataset.outstandingThreshold);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.querySelectorAll('[data-detail-tab]').forEach(button => {
        button.addEventListener('click', () => {
            const showInteractions = button.dataset.detailTab === 'interactions';
            detailPanel.classList.toggle('d-none', showInteractions);
            interactionsPanel.classList.toggle('d-none', !showInteractions);
            document.querySelectorAll('[data-detail-tab]').forEach(tab => {
                const active = tab === button;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', String(active));
            });
        });
    });

    const dependentOptions = {
        promise_to_pay: [['', 'Select...'], ['will_pay_today', 'Will pay today'], ['will_pay_within_3_days', 'Will pay within 3 days'], ['salary_not_yet_credited', 'Salary not yet credited'], ['bank_transfer_delay', 'Bank transfer delay']],
        temporary_financial_difficulty: [['', 'Select...'], ['salary_delay', 'Salary delay'], ['job_loss', 'Job loss'], ['medical_emergency', 'Medical emergency'], ['business_slowdown', 'Business slowdown'], ['seasonal_income_fluctuation', 'Seasonal income fluctuation']],
        billing_dispute: [['', 'Select...'], ['incorrect_bill_amount', 'Incorrect bill amount'], ['overcharging_complaint', 'Overcharging complaint'], ['double_billing', 'Double billing'], ['service_not_working_but_charged', 'Service not working but charged'], ['plan_mismatch', 'Plan mismatch'], ['previous_payment_not_updated', 'Previous payment not updated'], ['installation_issues', 'Installation issues']],
        service_quality: [['', 'Select...'], ['slow_internet', 'Slow internet'], ['frequent_disconnection', 'Frequent disconnection'], ['router_issue', 'Router issue'], ['fiber_damage', 'Fiber damage'], ['technical_complaint_pending', 'Technical complaint pending'], ['waiting_for_technician_visit', 'Waiting for technician visit']],
        intention_to_disconnect: [['', 'Select...'], ['wants_to_cancel_service', 'Wants to cancel service'], ['switching_to_competitor', 'Switching to a competitor'], ['moving_house', 'Moving house'], ['no_longer_needed', 'No longer needed'], ['temporary_relocator', 'Temporary relocator'], ['business_closed', 'Business closed']],
        contact_issue: [['', 'Select...'], ['no_answer', 'No answer'], ['switched_off', 'Switched off'], ['wrong_number', 'Wrong number'], ['number_unreachable', 'Number unreachable'], ['refused_to_talk', 'Refused to talk']],
        payment_behavior: [['', 'Select...'], ['always_pays_late', 'Always pays late'], ['habitual_suspend', 'Habitual suspend'], ['broken_ptp_multiple_times', 'Broken PTP multiple times'], ['demands_extensions_monthly', 'Demands extensions monthly']],
        escalation_risk: [['', 'Select...'], ['threatened_complaint_to_regulators', 'Threatened complaint to regulators'], ['angry_customer', 'Angry customer'], ['escalation_requested', 'Escalation requested'], ['legal_notice_mentioned', 'Legal notice mentioned']]
    };

    function renderDependentOptions(value) {
        if (system !== 'rb') return;
        const wrap = document.getElementById('ccDependentOutcomeWrap');
        const container = document.getElementById('ccDependentOutcomeContainer');
        container.replaceChildren();
        const options = dependentOptions[value];
        if (!options) {
            wrap.style.display = 'none';
            paymentWrap.style.display = 'none';
            expectedDate.value = '';
            return;
        }
        const select = document.createElement('select');
        select.name = 'outcome_detail';
        select.id = 'ccOutcomeDetail';
        select.className = 'form-select form-select-sm';
        select.disabled = !enabled || notRelevant.checked;
        options.forEach(([optionValue, label]) => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = label;
            select.append(option);
        });
        container.append(select);
        wrap.style.display = '';
        select.addEventListener('change', () => {
            if (value !== 'promise_to_pay' || !select.value) {
                paymentWrap.style.display = 'none';
                expectedDate.value = '';
                return;
            }
            paymentWrap.style.display = '';
            expectedDate.disabled = !enabled;
            expectedDate.min = localDate();
            if (select.value === 'will_pay_today') {
                expectedDate.value = localDate();
            } else if (select.value === 'will_pay_within_3_days') {
                expectedDate.value = localDate(3);
            } else {
                expectedDate.value = '';
            }
        });
    }

    function localDate(days = 0) {
        const date = new Date();
        date.setDate(date.getDate() + days);
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    }

    function setField(container, label, value) {
        const line = document.createElement('div');
        line.className = 'mb-2';
        const strong = document.createElement('strong');
        strong.textContent = `${label}: `;
        line.append(strong, document.createTextNode(value == null || value === '' ? '—' : String(value)));
        container.append(line);
    }

    function renderHistory(data) {
        interactionsPanel.replaceChildren();
        if (!Array.isArray(data.interactions) || data.interactions.length === 0) {
            interactionsPanel.textContent = 'No interactions recorded.';
            return;
        }
        const payments = new Map((data.payments || []).map(payment => [String(payment.interaction_id), payment]));
        const scroll = document.createElement('div');
        scroll.className = 'cc-interactions-scroll';
        const table = document.createElement('table');
        table.className = 'table table-sm mb-0 cc-interactions-table';
        const body = document.createElement('tbody');
        data.interactions.forEach(interaction => {
            const payment = payments.get(String(interaction.id));
            if (payment) {
                const row = body.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 2;
                cell.className = 'text-success';
                const lastContact = payment.last_contact_before_payment
                    ? `; Last contact before payment: ${payment.last_contact_before_payment}${payment.last_contact_before_payment_at ? ` on ${payment.last_contact_before_payment_at}` : ''}`
                    : '';
                cell.textContent = `PAYMENT — Paid Rs. ${payment.paid_amount ?? '—'} on ${payment.payment_date ?? '—'}; Recorded by: ${payment.paid_by_agent ?? '—'}${lastContact}`;
            }
            const row = body.insertRow();
            const agent = row.insertCell();
            agent.style.width = '38%';
            const name = document.createElement('div');
            name.className = 'fw-semibold';
            name.textContent = interaction.agent_name || `Agent #${interaction.agent_id || '—'}`;
            const time = document.createElement('div');
            time.className = 'text-muted small';
            time.textContent = interaction.created_at || '';
            agent.append(name, time);
            const description = row.insertCell();
            description.textContent = [interaction.outcome, interaction.outcome_detail, interaction.account_number ? `Acc: ${interaction.account_number}` : '', interaction.note].filter(Boolean).join(' — ') || '—';
        });
        table.append(body);
        scroll.append(table);
        interactionsPanel.append(scroll);
    }

    function renderDetails(data) {
        detailPanel.replaceChildren();
        document.getElementById('assignmentCustomerName').textContent =
            data.address_name || data.account_num || data.customer_ref || 'Customer';
        setField(detailPanel, 'Contact no', data.phone);
        setField(detailPanel, 'Telephone no', data.product_label);
        setField(detailPanel, 'Address', data.address);
        setField(detailPanel, 'RTO', data.rtom);
        setField(detailPanel, 'Customer ref', data.customer_ref);
        setField(detailPanel, 'Account #', data.account_num);
        setField(detailPanel, 'Sales person', data.sales_person);
        setField(detailPanel, 'Sales channel', data.sales_channel);
        renderHistory(data);

        document.getElementById('ccSelectedName').textContent = data.address_name || '';
        const amounts = document.getElementById('ccSelectedAmounts');
        amounts.replaceChildren();
        const outstanding = data.outstanding_balance == null ? null : Number(data.outstanding_balance);
        [
            ['Initial Outstanding', data.arrears],
            ['Bill', data.bill],
            ['Payment', data.payment_value],
            ['Current Outstanding', data.outstanding_balance],
            ['SMS Reminder 1', data.sms_1],
            ['SMS Reminder 2', data.sms_2]
        ].forEach(([label, value]) => setField(amounts, label, value == null ? null : `Rs. ${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`));
        setField(amounts, 'Payment status', outstanding == null ? '—' : (outstanding < threshold ? 'Paid' : 'Unpaid'));
        if (data.call_count) setField(amounts, 'Calls since assignment', data.call_count);
    }

    async function fetchDetails() {
        const response = await fetch(page.dataset.detailsUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
        if (!response.ok) throw new Error('Could not load assignment details.');
        return response.json();
    }

    fetchDetails().then(renderDetails).catch(error => {
        detailPanel.textContent = error.message || 'Failed to load assignment details.';
    });

    if (outcome) {
        outcome.addEventListener('change', () => {
            const value = outcome.value;
            if (system === 'rb') {
                renderDependentOptions(value);
            } else if (value === 'agreed to pay within 3 days' || value === 'agreed to pay within 7 days') {
                paymentWrap.style.display = '';
                document.getElementById('ccPaymentDateWrap').style.display = 'none';
                document.getElementById('ccPaidAmountWrap').style.display = 'none';
                expectedDate.value = localDate(value.includes('3 days') ? 3 : 7);
            } else if (value === 'paid') {
                paymentWrap.style.display = 'none';
                document.getElementById('ccPaymentDateWrap').style.display = '';
                document.getElementById('ccPaidAmountWrap').style.display = '';
                document.getElementById('ccPaymentDate').value = localDate();
            } else {
                paymentWrap.style.display = 'none';
                if (system === 'cc') {
                    document.getElementById('ccPaymentDateWrap').style.display = 'none';
                    document.getElementById('ccPaidAmountWrap').style.display = 'none';
                }
            }
        });
    }

    notRelevant.addEventListener('change', () => {
        if (notRelevant.checked) {
            outcome.value = '';
            outcome.disabled = true;
            paymentWrap.style.display = 'none';
            expectedDate.value = '';
            const dependent = document.getElementById('ccOutcomeDetail');
            if (dependent) dependent.disabled = true;
        } else if (enabled) {
            outcome.disabled = false;
            renderDependentOptions(outcome.value);
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!enabled) return;
        const formData = new FormData(form);
        if (notRelevant.checked) {
            formData.set('outcome', 'not relevant person contacted');
            ['payment_expected_at', 'payment_date', 'paid_amount', 'paid'].forEach(key => formData.delete(key));
        } else if (system === 'cc' && outcome.value === 'paid') {
            formData.set('paid', '1');
        }
        const submit = document.getElementById('ccSaveCallBtn');
        submit.disabled = true;
        status.textContent = 'Saving…';
        try {
            const response = await fetch(page.dataset.interactionsUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: formData
            });
            if (!response.ok) throw new Error('Save failed. Please check the entered values and try again.');
            form.reset();
            renderDependentOptions('');
            paymentWrap.style.display = 'none';
            if (system === 'cc') {
                document.getElementById('ccPaymentDateWrap').style.display = 'none';
                document.getElementById('ccPaidAmountWrap').style.display = 'none';
            }
            renderDetails(await fetchDetails());
            status.textContent = 'Saved.';
        } catch (error) {
            status.textContent = error.message || 'Save failed.';
        } finally {
            submit.disabled = false;
            if (!enabled) submit.disabled = true;
            if (notRelevant) notRelevant.checked = false;
            if (outcome) outcome.disabled = !enabled;
        }
    });
});
</script>
@endpush
