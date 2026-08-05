@extends('layouts.cc')

@section('title', 'Create RB Region Admin')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Regional Billing Centre Administration</p>
                        <h1 class="process-upload-title mb-0">Create Region Admin</h1>
                        <p class="text-muted small mb-0">
                            Assign a new RB region admin to a region from the last two reports,
                            or enter a custom region name.
                        </p>
                    </div>
                    <a href="{{ route('rb.regions.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Back</a>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('partials._region_create_form', [
                    'action'  => route('rb.super.store_user'),
                    'regions' => $regions,
                    'backUrl' => route('rb.regions.index'),
                ])
            </div>
        </div>
    </div>
</div>
@endsection
