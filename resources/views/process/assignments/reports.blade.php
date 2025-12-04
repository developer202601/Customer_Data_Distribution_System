@extends('layouts.admin')

@section('navbar-right')
<div class="process-stepper d-flex align-items-center gap-2">
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
    <span class="process-step completed"></span>
</div>
@if(session('user.is_admin'))
<a href="#" class="btn btn-outline-secondary">Configurations</a>
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
                            <option value="{{ $month }}" @if($month === $selectedMonth) selected @endif>{{ $month }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($generatorOptions->isNotEmpty())
                    <div class="col-auto">
                        <label for="report-generator" class="form-label mb-1">Filter by generator</label>
                        <select id="report-generator" name="generator" class="form-select" onchange="this.form.submit()">
                            <option value="" @if($selectedGenerator === null) selected @endif>All generators</option>
                            @foreach($generatorOptions as $key => $label)
                            <option value="{{ $key }}" @if((string) $key === (string) $selectedGenerator) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </form>

                <div class="process-table-container">
                    <div class="table-responsive">
                        <table class="table table-striped process-table align-middle mb-0">
                            <thead>
                                <tr>
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
                                    $datasetLabel = $processRow->dataset_month ? Carbon::parse($processRow->dataset_month . '-01')->format('M Y') : 'Manual upload';
                                    $generatedAt = $processRow->run_date ? Carbon::parse($processRow->run_date)->format('d M Y – H:i') : $processRow->created_at->format('d M Y – H:i');
                                    $statusLabel = ucfirst($processRow->status ?? 'ready');
                                    $badgeColor = $processRow->status === 'failed' ? 'danger' : 'success';
                                    $generatorName = $processRow->user?->username ?? $processRow->user_name ?? 'System';
                                @endphp
                                <tr>
                                    <td>{{ $datasetLabel }}</td>
                                    <td>{{ $generatedAt }}</td>
                                    <td>{{ $generatorName }}</td>
                                    <td class="text-end">{{ number_format($processRow->row_count ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($processRow->excluded_count ?? 0) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $badgeColor }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="text-end d-flex justify-content-end gap-2">
                                        <a href="{{ route('process.assignments.report', ['process' => $processRow]) }}" class="btn btn-outline-primary btn-sm" data-loader-off="1">View assignments</a>
                                        @if(session('user.is_admin'))
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#datasetDeleteModal"
                                            data-dataset-delete-trigger="true"
                                            data-delete-action="{{ route('process.assignments.destroy', ['process' => $processRow]) }}"
                                            data-delete-label="{{ $datasetLabel }} · {{ $generatorName }} · {{ $generatedAt }}"
                                        >
                                            Delete dataset
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No reports generated for this month.</td>
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

@if(session('user.is_admin'))
<div class="modal fade" id="datasetDeleteModal" tabindex="-1" aria-labelledby="datasetDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-sm rounded">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="datasetDeleteModalLabel">Delete dataset?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-white">
                    <p class="mb-2 text-muted">You are about to delete this dataset and all associated cached exports. This action cannot be undone.</p>
                    <p class="fw-semibold mb-0" id="dataset-delete-title">Dataset details</p>
                </div>
                <div class="modal-footer justify-content-between bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="dataset-delete-form" method="post" action="" class="mb-0">
                        @csrf
                        @method('delete')
                        <button type="submit" class="btn btn-danger">Delete dataset</button>
                    </form>
                </div>
            </div>
        </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('datasetDeleteModal');
        if (!modalEl) return;
        const deleteForm = document.getElementById('dataset-delete-form');
        const deleteTitle = document.getElementById('dataset-delete-title');

        modalEl.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            deleteForm.action = trigger.getAttribute('data-delete-action') || '';
            deleteTitle.textContent = trigger.getAttribute('data-delete-label') || 'Selected dataset';
        });
    });
</script>
@endpush
@endif
@endsection
