@extends('layouts.cc')

@section('title', 'Create RB Region Admin')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Create RB Region Admin</h1>
                        <p class="text-muted small mb-0">
                            Creates a Regional Billing region admin and assigns them to a region
                            from the last two reports, or a custom region name.
                        </p>
                    </div>
                    <a href="{{ route('cc.super.segments') }}" class="btn btn-outline-secondary rounded-pill px-4">Back</a>
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
                    'action'  => route('cc.super.rb_region.store'),
                    'regions' => $regions,
                    'backUrl' => route('cc.super.segments'),
                ])
            </div>
        </div>
    </div>
</div>
@endsection
