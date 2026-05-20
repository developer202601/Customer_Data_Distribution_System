@extends('layouts.cc')

@section('title', 'RTO Dashboard')

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
                        <p class="text-uppercase text-muted mb-1">Regional Billing — RTO Dashboard</p>
                        <h1 class="process-upload-title mb-0">{{ $rtom }}</h1>
                        <p class="text-muted">Callers owned: {{ $callers->count() }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('rb.users.index') }}" class="btn btn-outline-primary rounded-pill px-4">Manage Callers</a>
                        <a href="{{ route('rb.reports') }}" class="btn btn-outline-success rounded-pill px-4">RBC Reports</a>
                        <a href="{{ route('rb.region.create_supervisor') }}" class="btn btn-outline-secondary rounded-pill px-4">Add Caller</a>
                    </div>
                </div>

                <div class="table-responsive cc-table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($callers as $caller)
                                <tr>
                                    <td><strong>{{ $caller->username }}</strong></td>
                                    <td>{{ $caller->name ?? '—' }}</td>
                                    <td>Caller</td>
                                    <td><small>{{ $caller->created_at?->format('M d, Y') ?? '—' }}</small></td>
                                    <td class="text-end">
                                        <a href="{{ route('rb.users.edit', $caller->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No callers assigned</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection