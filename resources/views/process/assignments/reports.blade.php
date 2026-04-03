@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
</div>
@if(session('user.is_admin'))
<a href="{{ route('admin.config') }}" class="btn btn-outline-secondary">Configurations</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
@php use Illuminate\Support\Carbon; @endphp
<div class="process-preview p-4 p-lg-5 shadow-sm">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="process-preview-title mb-2">Report archive</h1>
                <p class="text-muted mb-1">Browse every past dataset export grouped by calendar month.</p>
                <p class="text-muted mb-0">Select a month, review the generated summaries, and jump back to assignments any time.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary" data-loader-off="1">Back</a>
            </div>
        </div>

        @if($reportGroups->isEmpty())
        <div class="alert alert-secondary mb-0" role="status">
            No exported reports are available yet. Run a dataset process to see its history here.
        </div>
        @else
        <div class="row gy-4">
            <div class="col-12">
                <form class="row g-3 align-items-end mb-4" method="get" action="{{ route('process.assignments.reports') }}" data-loader-off="1">
                    <div class="col-auto">
                        <label for="report-month" class="form-label mb-1">Filter by month</label>
                        <select id="report-month" name="month" class="form-select" onchange="this.form.submit()">
                            @foreach($monthOptions as $month)
                            <option value="{{ $month }}" @if($month===$selectedMonth) selected @endif>{{ $month }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($generatorOptions->isNotEmpty())
                    <div class="col-auto">
                        <label for="report-generator" class="form-label mb-1">Filter by generator</label>
                        <select id="report-generator" name="generator" class="form-select" onchange="this.form.submit()">
                            <option value="" @if($selectedGenerator===null) selected @endif>All generators</option>
                            @foreach($generatorOptions as $key => $label)
                            <option value="{{ $key }}" @if((string) $key===(string) $selectedGenerator) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </form>

                @if(session('user.is_admin') && $filteredProcesses->isNotEmpty())
                <div class="d-flex justify-content-end gap-2 mb-3" id="report-delete-controls">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="enter-delete-mode">Select datasets to delete</button>
                </div>
                <form id="bulk-delete-form" method="post" action="{{ route('process.assignments.destroyBulk') }}" class="mb-3 d-none" data-loader-off="1">
                    @csrf
                    @method('delete')
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancel-delete-mode">Exit selection mode</button>
                        <button type="submit" class="btn btn-outline-danger btn-sm" id="bulk-delete-button" disabled>
                            Delete selected
                        </button>
                    </div>
                </form>
                @endif

                <div class="process-table-container">
                    <div class="table-responsive">
                        <table class="table table-striped process-table align-middle mb-0">
                            <thead>
                                <tr>
                                    @if(session('user.is_admin'))
                                    <th scope="col" class="report-select-column d-none" style="width: 40px;">
                                        <input type="checkbox" id="select-all-reports" aria-label="Select all datasets">
                                    </th>
                                    @endif
                                    <th scope="col">Dataset month</th>
                                    <th scope="col">Generated at</th>
                                    <th scope="col">Generator</th>
                                    <th scope="col" class="text-end">Rows</th>
                                    <th scope="col" class="text-end">Excluded</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($filteredProcesses as $processRow)
                                @php
                                $datasetLabel = 'Manual upload';
                                if ($processRow->dataset_month) {
                                try {
                                $datasetLabel = Carbon::createFromFormat('Ym', (string) $processRow->dataset_month)->format('M Y');
                                } catch (\Throwable) {
                                $datasetLabel = (string) $processRow->dataset_month;
                                }
                                }

                                // "Generated at" represents when this run was created in CDDS,
                                // not the workbook's internal run_date.
                                $generatedAt = $processRow->created_at
                                ? $processRow->created_at->format('d M Y – H:i')
                                : Carbon::now()->format('d M Y – H:i');
                                $statusRaw = (string) ($processRow->status ?? 'ready');
                                $statusLabel = match ($statusRaw) {
                                'exports_pending' => 'Generating exports',
                                'canceled' => 'Canceled',
                                default => ucfirst(str_replace('_', ' ', $statusRaw)),
                                };
                                $badgeColor = match ($statusRaw) {
                                'failed' => 'danger',
                                'ready' => 'success',
                                'exports_pending' => 'warning',
                                default => 'secondary',
                                };
                                $generatorName = $processRow->user?->username ?? $processRow->user_name ?? 'System';
                                $isContinuable = ! in_array($statusRaw, ['ready', 'failed', 'canceled'], true);
                                $actionLabel = $statusRaw === 'exports_pending' ? 'View assignments' : ($isContinuable ? 'Continue' : 'View assignments');
                                @endphp
                                <tr class="report-row-selectable">
                                    @if(session('user.is_admin'))
                                    <td class="report-select-column d-none">
                                        <input
                                            type="checkbox"
                                            class="report-select"
                                            name="process_ids[]"
                                            value="{{ $processRow->id }}"
                                            form="bulk-delete-form"
                                            aria-label="Select dataset {{ $datasetLabel }}"
                                            data-dataset-label="{{ $datasetLabel }}"
                                            data-dataset-status="{{ $statusLabel }}">
                                    </td>
                                    @endif
                                    <td>{{ $datasetLabel }}</td>
                                    <td>{{ $generatedAt }}</td>
                                    <td>{{ $generatorName }}</td>
                                    <td class="text-end">{{ number_format($processRow->row_count ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($processRow->excluded_count ?? 0) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $badgeColor }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="text-end d-flex justify-content-end gap-2">
                                        <a href="{{ route('process.assignments.report', ['process' => $processRow]) }}" class="btn btn-outline-primary btn-sm" data-loader-off="1">{{ $actionLabel }}</a>

                                        @if(!in_array($statusRaw, ['ready', 'canceled'], true))
                                        <form method="post" action="{{ route('process.assignments.cancel', ['process' => $processRow]) }}" class="d-inline" data-loader-off="1" onsubmit="return confirm('Cancel this dataset process?');">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ session('user.is_admin') ? 8 : 7 }}" class="text-center py-4 text-muted">No reports generated for this month.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<div class="modal fade" id="bulkDeleteConfirmModal" tabindex="-1" aria-labelledby="bulkDeleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-sm rounded">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title" id="bulkDeleteConfirmModalLabel">Confirm dataset deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-white">
                <p class="mb-2 text-muted">You are about to delete the selected datasets and all related files/exports. This action cannot be undone.</p>
                <div class="border rounded-3 p-2" style="max-height: 260px; overflow-y: auto;">
                    <ul id="bulk-delete-selection-list" class="mb-0 ps-3"></ul>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="bulk-delete-confirm-button">Delete selected</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('select-all-reports');
        const selectColumns = Array.from(document.querySelectorAll('.report-select-column'));
        const checkboxes = Array.from(document.querySelectorAll('.report-select'));
        const enterDeleteModeButton = document.getElementById('enter-delete-mode');
        const cancelDeleteModeButton = document.getElementById('cancel-delete-mode');
        const deleteControls = document.getElementById('report-delete-controls');
        const bulkDeleteForm = document.getElementById('bulk-delete-form');
        const bulkDeleteButton = document.getElementById('bulk-delete-button');
        const selectionList = document.getElementById('bulk-delete-selection-list');
        const confirmDeleteButton = document.getElementById('bulk-delete-confirm-button');
        const confirmModalEl = document.getElementById('bulkDeleteConfirmModal');
        const confirmModal = confirmModalEl && window.bootstrap ? new window.bootstrap.Modal(confirmModalEl) : null;
        let deleteMode = false;
        let confirmedSubmit = false;

        if (!bulkDeleteForm || !bulkDeleteButton || !enterDeleteModeButton) {
            return;
        }

        const setDeleteMode = (enabled) => {
            deleteMode = enabled;

            selectColumns.forEach((column) => {
                column.classList.toggle('d-none', !enabled);
            });

            deleteControls?.classList.toggle('d-none', enabled);
            bulkDeleteForm.classList.toggle('d-none', !enabled);

            if (!enabled) {
                checkboxes.forEach((entry) => {
                    entry.checked = false;
                });
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
            }

            refreshBulkState();
        };

        const refreshBulkState = () => {
            const selectedCount = checkboxes.filter((entry) => entry.checked).length;
            bulkDeleteButton.disabled = !deleteMode || selectedCount === 0;
            bulkDeleteButton.textContent = selectedCount > 0 ?
                `Delete selected (${selectedCount})` :
                'Delete selected';

            if (selectAll) {
                const allSelected = checkboxes.length > 0 && selectedCount === checkboxes.length;
                selectAll.checked = allSelected;
                selectAll.indeterminate = selectedCount > 0 && !allSelected;
            }
        };

        selectAll?.addEventListener('change', () => {
            checkboxes.forEach((entry) => {
                entry.checked = selectAll.checked;
            });
            refreshBulkState();
        });

        checkboxes.forEach((entry) => {
            entry.addEventListener('change', refreshBulkState);
        });

        Array.from(document.querySelectorAll('tr.report-row-selectable')).forEach((row) => {
            row.addEventListener('click', (event) => {
                if (!deleteMode) {
                    return;
                }

                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                if (target.closest('a, button, input, label, select, textarea, form')) {
                    return;
                }

                const checkbox = row.querySelector('.report-select');
                if (!(checkbox instanceof HTMLInputElement)) {
                    return;
                }

                checkbox.checked = !checkbox.checked;
                refreshBulkState();
            });
        });

        enterDeleteModeButton.addEventListener('click', () => setDeleteMode(true));
        cancelDeleteModeButton?.addEventListener('click', () => setDeleteMode(false));

        bulkDeleteForm?.addEventListener('submit', (event) => {
            if (confirmedSubmit) {
                confirmedSubmit = false;
                return;
            }

            const selectedCount = checkboxes.filter((entry) => entry.checked).length;
            if (selectedCount === 0) {
                event.preventDefault();
                return;
            }

            event.preventDefault();

            if (!confirmModal || !selectionList || !confirmDeleteButton) {
                if (window.confirm(`Delete ${selectedCount} selected dataset(s)? This cannot be undone.`)) {
                    confirmedSubmit = true;
                    bulkDeleteForm.submit();
                }
                return;
            }

            const selectedItems = checkboxes
                .filter((entry) => entry.checked)
                .map((entry) => ({
                    label: entry.getAttribute('data-dataset-label') || 'Dataset',
                    status: entry.getAttribute('data-dataset-status') || 'Unknown',
                }));

            selectionList.innerHTML = '';
            selectedItems.forEach((item) => {
                const li = document.createElement('li');
                li.textContent = `${item.label} (${item.status})`;
                selectionList.appendChild(li);
            });

            confirmDeleteButton.onclick = () => {
                confirmModal.hide();
                confirmedSubmit = true;
                bulkDeleteForm.submit();
            };

            confirmModal.show();
        });

        setDeleteMode(false);
    });
</script>
@endpush
@endsection