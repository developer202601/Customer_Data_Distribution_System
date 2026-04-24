@extends('layouts.cc')

@section('title', 'Region Admins (CC & RBC)')

@section('navbar-right')
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">System Administration</p>
                        <h1 class="process-upload-title mb-0">Regions & Region Admins</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('rb.users.create') }}" class="btn btn-outline-success rounded-pill px-4">Add Region Admin</a>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h2 class="h6 mb-0">Region Admins</h2>
                            <form method="get" action="{{ route('rb.regions.index') }}" class="d-flex gap-2 flex-wrap" id="rb-super-regions-filter">
                                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search username or name" value="{{ old('q', $q ?? request('q')) }}" style="width: 200px;">
                                <select name="system" class="form-select form-select-sm" style="width: 150px;">
                                    <option value="">All Systems</option>
                                    <option value="cc" {{ (string)($selectedSystem ?? request('system')) === 'cc' ? 'selected' : '' }}>Call Center</option>
                                    <option value="rb" {{ (string)($selectedSystem ?? request('system')) === 'rb' ? 'selected' : '' }}>Regional Billing</option>
                                </select>
                                <select name="region" class="form-select form-select-sm" style="width: 150px;">
                                    <option value="">All Regions</option>
                                    @foreach(($regions ?? collect()) as $r)
                                        <option value="{{ $r }}" {{ (string)($selectedRegion ?? request('region')) === (string)$r ? 'selected' : '' }}>{{ $r }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>

                        <div class="table-responsive cc-table-container">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>System</th>
                                        <th>Region</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="rb-region-rows">
                                    @forelse($regionAdmins as $admin)
                                        <tr>
                                            <td><strong>{{ $admin->username }}</strong></td>
                                            <td>{{ $admin->name ?? '—' }}</td>
                                            <td>
                                                <span class="badge {{ $admin->system === 'cc' ? 'bg-primary' : 'bg-success' }}">
                                                    {{ $admin->system === 'cc' ? 'Call Center' : 'Regional Billing' }}
                                                </span>
                                            </td>
                                            <td>{{ $admin->assignment ?? '—' }}</td>
                                            <td><small>{{ $admin->created_at?->format('M d, Y') ?? '—' }}</small></td>
                                            <td class="text-end">
                                                <a href="{{ route('rb.regions.edit', $admin->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No region admins found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
