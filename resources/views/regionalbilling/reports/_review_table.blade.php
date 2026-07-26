@if($selectedReport)
    <!-- File Upload Submission Section -->
    <div class="row g-3 w-100 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 bg-light rounded-4 h-100">
                <div class="card-body p-3">
                    <p class="text-uppercase text-muted small mb-1 fw-bold">Exclude file submission</p>
                    <p class="small text-muted mb-2">Upload a workbook of rows to hide from the review set.</p>
                    <button
                        type="button"
                        class="btn btn-outline-danger btn-sm px-3"
                        data-bs-toggle="modal"
                        data-bs-target="#excludeFileModal"
                        data-report-id="{{ $selectedReport->id }}"
                    >
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload Exclude File
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 bg-light rounded-4 h-100">
                <div class="card-body p-3">
                    <p class="text-uppercase text-muted small mb-1 fw-bold">Inclusion file submission</p>
                    <p class="small text-muted mb-2">Upload a workbook of rows to keep visible and hide everything else.</p>
                    <button
                        type="button"
                        class="btn btn-outline-success btn-sm px-3"
                        data-bs-toggle="modal"
                        data-bs-target="#includeFileModal"
                        data-report-id="{{ $selectedReport->id }}"
                    >
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload Inclusion File
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- RTOM Releases Section -->
    <div class="card border-0 bg-light rounded-4 mb-4">
        <div class="card-body p-4">
            <p class="text-uppercase text-muted small mb-1 fw-bold">RTOM Releases</p>
            <p class="text-muted small mb-3">Release records to specific RTOMs under your region once you have completed your review. Remaining RTOMs can be passed later.</p>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>RTOM Name</th>
                            <th>Record Count</th>
                            <th>Status / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rtomsWithDetails ?? [] as $rtom)
                            <tr>
                                <td class="fw-bold">{{ strtoupper($rtom['name']) }}</td>
                                <td><span class="badge text-bg-secondary">{{ number_format($rtom['count']) }} records</span></td>
                                <td>
                                    @if($rtom['is_passed'])
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="text-success small fw-semibold">
                                                <i class="bi bi-check-circle-fill"></i> Passed at {{ $rtom['passed_at']->format('Y-m-d H:i') }} by {{ $rtom['passed_by'] }}
                                            </span>
                                            @if(!empty($canUnlockReview))
                                                <form method="post" action="{{ route('rb.reports.unlock', $selectedReport->id) }}" class="m-0 d-inline">
                                                    @csrf
                                                    <input type="hidden" name="rtom" value="{{ $rtom['name'] }}">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill px-3">Unlock</button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <form method="post" action="{{ route('rb.reports.pass', $selectedReport->id) }}" class="m-0">
                                            @csrf
                                            <input type="hidden" name="rtom" value="{{ $rtom['name'] }}">
                                            <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Pass to RTOM {{ strtoupper($rtom['name']) }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted text-center py-3">No RTOM records found for this report in your region.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bulk actions rows form wrapper -->
    <form id="bulkRowsForm" method="post" action="{{ route('rb.reports.hide_rows', $selectedReport->id) }}">
        @csrf
        <input type="hidden" name="action" id="bulkAction" value="hide">

        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if(!empty($isLocked))
                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Review Locked</button>
                @endif
                <div class="form-check ms-2">
                    <input class="form-check-input" type="checkbox" id="selectAllRows" {{ !empty($isLocked) ? 'disabled' : '' }}>
                    <label class="form-check-label small" for="selectAllRows">Select all in current page</label>
                </div>

                <div class="vr mx-1 d-none d-md-block"></div>

                <div class="form-check ms-1">
                    <input class="form-check-input" type="checkbox" id="showHiddenRowsToggle" {{ !empty($showHidden) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="showHiddenRowsToggle">Show hidden rows</label>
                </div>
                <div class="form-check ms-1">
                    <input class="form-check-input" type="checkbox" id="showHiddenOnlyRowsToggle" {{ !empty($showHiddenOnly) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="showHiddenOnlyRowsToggle">Hidden only</label>
                </div>
            </div>
            <div style="min-width: 320px;" class="w-100 w-md-auto">
                <label class="form-label small text-muted mb-1" for="tableSearch">Search</label>
                <input class="form-control form-control-sm" type="search" id="tableSearch" name="q" value="{{ $search ?? request('q') }}" placeholder="Account / arrears / phone / customer ref">
            </div>
        </div>

        <div class="table-responsive cc-table-container">
            <table class="table align-middle mb-0" id="reviewRowsTable">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Account Number</th>
                    <th>Arrears</th>
                    <th>Payment</th>
                    <th>Phone</th>
                    <th>Customer Ref</th>
                    <th>Status</th>
                    <th style="width: 120px;">More</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr
                        class="review-row"
                        data-row-id="{{ $row->id }}"
                        data-row-visibility="{{ !empty($row->is_hidden_for_distribution) ? 'hidden' : 'visible' }}"
                        data-row-label="{{ trim(($row->account_num ? ('Account ' . $row->account_num) : ('Row #' . $row->id)) . ($row->customer_ref ? (' | Ref ' . $row->customer_ref) : '') . ($row->mobile_contact_tel ? (' | Tel ' . $row->mobile_contact_tel) : '')) }}"
                    >
                        <td>
                            @php
                                $isRtomPassed = in_array(strtolower(trim($row->rtom)), $passedRtomNames ?? [], true);
                            @endphp
                            <input class="form-check-input row-check" type="checkbox" name="row_ids[]" value="{{ $row->id }}" {{ (!empty($isLocked) || $isRtomPassed) ? 'disabled' : '' }}>
                        </td>
                        <td>{{ $row->account_num ?? '—' }}</td>
                        <td>{{ $row->new_arrears_value !== null ? number_format((float) $row->new_arrears_value, 2) : '—' }}</td>
                        <td class="text-danger">{{ $row->payments_value !== null ? number_format((float) $row->payments_value, 2) : '—' }}</td>
                        <td>{{ $row->mobile_contact_tel ?? '—' }}</td>
                        <td>{{ $row->customer_ref ?? '—' }}</td>
                        <td>
                            @if(!empty($row->is_hidden_for_distribution))
                                <span class="badge text-bg-danger">Hidden</span>
                            @else
                                <span class="badge text-bg-success">Visible</span>
                            @endif
                            @if($isRtomPassed)
                                <span class="badge text-bg-secondary" title="Passed to RTOM"><i class="bi-lock-fill"></i> Passed</span>
                            @endif
                        </td>
                        <td>
                            <button
                                class="btn btn-sm btn-outline-primary row-details-btn"
                                type="button"
                                data-target-row-id="more-{{ $row->id }}"
                                data-collapsed-text="More details"
                                data-expanded-text="Collapse details"
                                aria-expanded="false"
                                aria-controls="more-{{ $row->id }}"
                            >More details</button>
                        </td>
                    </tr>
                    <tr class="d-none" id="more-{{ $row->id }}">
                        <td colspan="8" class="bg-light">
                            <div class="small">
                                <strong>Address:</strong> {{ $row->full_address ?? '—' }}<br>
                                <strong>Address Name:</strong> {{ $row->address_name ?? '—' }}<br>
                                <strong>RTOM:</strong> {{ $row->rtom ?? '—' }}<br>
                                <strong>Region:</strong> {{ $row->region ?? '—' }}<br>
                                <strong>Sales Person:</strong> {{ $row->sales_person ?? '—' }}<br>
                                <strong>Sales Channel:</strong> {{ $row->sales_channel ?? '—' }}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">No rows found for this report/region with current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="mt-3" id="reviewRowsPagination">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small text-muted">
                <div>
                    @if ($rows->count())
                        Showing {{ number_format($rows->firstItem()) }}
                        to {{ number_format($rows->lastItem()) }}
                        of {{ number_format($rows->total()) }} rows
                        ({{ number_format($rows->perPage()) }} per page)
                    @else
                        No records found
                    @endif
                </div>

                <div>
                    {{ $rows->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    @endif
    </form>
@endif
