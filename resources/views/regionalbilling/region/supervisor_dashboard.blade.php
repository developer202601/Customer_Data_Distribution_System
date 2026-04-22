@extends('layouts.cc')

@section('title', 'Supervisor Dashboard')

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
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Supervisor Dashboard</p>
                        <h1 class="process-upload-title mb-0">Supervisor: {{ $rtom }}</h1>
                        <p class="text-muted">Callers assigned: {{ $callers->count() }}</p>
                    </div>
                </div>

                <div class="table-responsive cc-table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Assignment</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($callers as $caller)
                                <tr>
                                    <td><strong>{{ $caller->username }}</strong></td>
                                    <td>{{ $caller->name ?? '—' }}</td>
                                    <td>{{ $caller->assignment }}</td>
                                    <td><small>{{ $caller->created_at?->format('M d, Y') ?? '—' }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No callers assigned yet.</td>
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