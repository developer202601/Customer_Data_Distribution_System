@extends('layouts.cc')

@section('title', 'Assignment details')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary ms-2">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">{{ $system === 'cc' ? 'Call Center' : 'Regional Billing' }}</p>
                        <h1 class="h4 mb-0">Assignment details</h1>
                        <div id="assignmentCustomerName" class="text-muted mt-1">Loading customer details…</div>
                    </div>
                    <a href="{{ route($system . '.assignments.manage') }}" class="btn btn-outline-secondary">Back to assigned rows</a>
                </div>
                <div id="assignmentPage"
                     data-system="{{ $system }}"
                     data-assignment-id="{{ $assignment->id }}"
                     data-details-url="{{ route($system . '.assignments.details', $assignment->id) }}"
                     data-interactions-url="{{ route($system . '.assignments.interactions.store', $assignment->id) }}"
                     data-interaction-enabled="{{ $interactionEnabled ? '1' : '0' }}"
                     data-outstanding-threshold="{{ $outstandingThreshold }}">
                    @include('assignments.partials.detail-content')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
