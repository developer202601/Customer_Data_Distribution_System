@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Not Available</p>
                        <h1 class="process-upload-title mb-0">Direct user creation is disabled</h1>
                    </div>
                </div>

                <p class="text-muted">Region admins may only create RTO admins. Use the <strong>Create RTO Admin</strong> action instead.</p>

                <a href="{{ route('cc.region.index') }}" class="btn btn-outline-secondary">Back</a>

            </div>
        </div>
    </div>
</div>
@endsection
