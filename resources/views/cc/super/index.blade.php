@extends('layouts.cc')

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
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Assign Users</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.users.create') }}" class="btn btn-outline-primary rounded-pill px-4">Add User</a>
                        <a href="{{ route('cc.users.index') }}" class="btn btn-outline-success rounded-pill px-4">Manage Users</a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="table-responsive cc-table-container">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Assignment</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($users as $u)
                                        <tr>
                                            <td>{{ $u->username }}</td>
                                            <td>{{ $u->name ?? '—' }}</td>
                                            <td>{{ $u->assignment ?? 'none' }}</td>
                                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('cc.users.assign', $u) }}">{{ $u->assignment ? 'Change Assignment' : 'Assign' }}</a></td>
                                        </tr>
                                    @endforeach
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
