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
                        <p class="text-uppercase text-muted mb-1">Call Center — Region: {{ $region }}</p>
                        <h1 class="process-upload-title mb-0">Callers</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.region.callers.create') }}" class="btn btn-outline-success rounded-pill px-4">Add Caller</a>
                    </div>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

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
                            @forelse($callers as $user)
                                <tr>
                                    <td>{{ $user->username }}</td>
                                    <td>{{ $user->name ?? '—' }}</td>
                                    <td>
                                        @if($user->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('cc.region.callers.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        @if($user->status)
                                            <form action="{{ route('cc.region.callers.disable', $user) }}" method="post" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Disable</button>
                                            </form>
                                        @else
                                            <form action="{{ route('cc.region.callers.enable', $user) }}" method="post" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-outline-success">Enable</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('cc.region.callers.destroy', $user) }}" method="post" class="d-inline" onsubmit="return confirm('Delete this caller?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
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
