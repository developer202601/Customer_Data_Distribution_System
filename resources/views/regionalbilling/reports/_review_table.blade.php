@if($selectedReport)
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            @if(!empty($isLocked))
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Review Locked</button>
            @endif
            <div class="form-check ms-2">
                <input class="form-check-input" type="checkbox" id="selectAllRows">
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
                            <input class="form-check-input row-check" type="checkbox" name="row_ids[]" value="{{ $row->id }}">
                        </td>
                        <td>{{ $row->account_num ?? '—' }}</td>
                        <td>{{ $row->new_arrears_value !== null ? number_format((float) $row->new_arrears_value, 2) : '—' }}</td>
                        <td>{{ $row->mobile_contact_tel ?? '—' }}</td>
                        <td>{{ $row->customer_ref ?? '—' }}</td>
                        <td>
                            @if(!empty($row->is_hidden_for_distribution))
                                <span class="badge text-bg-danger">Hidden</span>
                            @else
                                <span class="badge text-bg-success">Visible</span>
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
                        <td colspan="7" class="bg-light">
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
@endif
