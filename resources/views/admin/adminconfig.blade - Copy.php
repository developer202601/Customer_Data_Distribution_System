@extends('layouts.admin')

@section('navbar-right')
@if(session('user.is_admin'))
<a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mr-2">Return To Main</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="container-fluid py-3">
    <div class="row g-4">
        <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="text-muted small">Customer Data Distribution</div>
                <h2 class="mb-1 fw-semibold">Configurations</h2>
                <div class="text-muted">Adjust the master dataset thresholds and business quotas. Every edit is audited and applied live.</div>
            </div>
            <div class="d-flex flex-column text-end">
                <span class="small text-muted">Live datasets</span>
                <strong>{{ number_format(session('master.dataset.process_id') ? 1 : 0) }}</strong>
                <span class="small text-muted mt-1">Acting user</span>
                <strong>{{ session('user.username') ?? 'System' }}</strong>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="config-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1">Bill Value Range</h5>
                        <div class="text-muted small">Control the upper and lower bill thresholds.</div>
                    </div>
                    @if(!empty($billRangeUpdated['timestamp']))
                    <div class="text-end small text-muted">
                        <div>Last edited: {{ optional($billRangeUpdated['timestamp'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                        <div>By: {{ $billRangeUpdated['editor']->username ?? $billRangeUpdated['editor']->name ?? 'Unknown' }}</div>
                    </div>
                    @endif
                </div>
                <form action="{{ route('configurations.billrange') }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    @method('post')
                    <div class="col-12 col-md-6">
                        <label class="form-label">Upper Range</label>
                        <input type="number" name="upper_range" value="{{ $configs['upper_range']->value ?? '' }}" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Lower Range</label>
                        <input type="number" name="lower_range" value="{{ $configs['lower_range']->value ?? '' }}" class="form-control" required>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="config-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1">No Of Accounts</h5>
                        <div class="text-muted small">Update the Bill Areas quota.</div>
                    </div>
                    @if(!empty($staffUpdated['timestamp']))
                    <div class="text-end small text-muted">
                        <div>Last edited: {{ optional($staffUpdated['timestamp'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                        <div>By: {{ $staffUpdated['editor']->username ?? $staffUpdated['editor']->name ?? 'Unknown' }}</div>
                    </div>
                    @endif
                </div>
                <form action="{{ route('configurations.billarears') }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    @method('post')
                    <div class="col-12 col-md-4">
                        <label class="form-label">Call Centre Staff</label>
                        <input type="number" name="ccs" value="{{ $configs['ccs']->value ?? '' }}" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Call Centre</label>
                        <input type="number" name="cc" value="{{ $configs['cc']->value ?? '' }}" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Staff</label>
                        <input type="number" name="s" value="{{ $configs['s']->value ?? '' }}" class="form-control" required>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="config-card">
                <h5 class="mb-1">User account controls</h5>
                <div class="text-muted small mb-2">User provisioning and access control is managed outside this tool. Use the IDM console when you need to update accounts.</div>
                <p class="mb-0 text-muted">Need a user change? Contact Security and they will take care of provisioning for you.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
:root {
    --config-border: rgba(0, 0, 0, 0.08);
}

.config-card {
    border: 1px solid var(--config-border);
    border-radius: 12px;
    padding: 18px 20px;
    background: transparent;
}

.config-card h5 {
    font-weight: 600;
}
</style>
@endpush

@push('scripts')
@endpush
