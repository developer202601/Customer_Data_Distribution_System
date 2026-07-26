@extends('layouts.cc')

@section('title', 'Callers')

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
                        <p class="text-uppercase text-muted mb-1">Call Center — Segment: {{ $segmentLabel }}</p>
                        <h1 class="process-upload-title mb-0">Callers</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.segment.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Dashboard</a>
                        <a href="{{ route('cc.segment.callers.create') }}" class="btn btn-outline-success rounded-pill px-4">Add Caller</a>
                    </div>
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

                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <form method="get" class="d-flex gap-2 mb-4 flex-wrap">
                    <input type="search" name="q" class="form-control form-control-sm" style="max-width:260px;"
                        placeholder="Search username or name" value="{{ $q }}">
                    <select name="status" class="form-select form-select-sm" style="max-width:160px;" onchange="this.form.submit()">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All statuses</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="disabled" {{ $statusFilter === 'disabled' ? 'selected' : '' }}>Disabled</option>
                    </select>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
                </form>

                <div class="table-responsive cc-table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($callers as $caller)
                                <tr>
                                    <td>{{ $caller->username }}</td>
                                    <td>{{ $caller->name ?? '—' }}</td>
                                    <td>
                                        @if($caller->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('cc.segment.callers.edit', $caller) }}"
                                            class="btn btn-sm btn-outline-primary">Edit</a>
                                        @if($caller->status)
                                            <form action="{{ route('cc.segment.callers.disable', $caller) }}"
                                                method="post" class="d-inline">
                                                @csrf @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Disable</button>
                                            </form>
                                        @else
                                            <form action="{{ route('cc.segment.callers.enable', $caller) }}"
                                                method="post" class="d-inline">
                                                @csrf @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-outline-success">Enable</button>
                                            </form>
                                        @endif
                                        @if(!$caller->fixed)
                                            <form action="{{ route('cc.segment.callers.destroy', $caller) }}"
                                                method="post" class="d-inline"
                                                onsubmit="return confirm('Delete this caller?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">No callers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
