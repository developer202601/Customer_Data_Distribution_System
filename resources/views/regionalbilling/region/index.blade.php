@extends('layouts.cc')

@section('title', 'RTOs & RTO Admins')

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
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Region: {{ $region }}</p>
                        <h1 class="process-upload-title mb-0">RTOs & RTO Admins</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('rb.region.create_admin') }}" class="btn btn-outline-success rounded-pill px-4">Add RTO Admin</a>
                        <a href="{{ route('rb.region.create_supervisor') }}" class="btn btn-outline-primary rounded-pill px-4">Add Supervisor</a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 mb-0">RTO Admins</h2>
                            <form method="get" action="{{ route('rb.region.index') }}" class="d-flex gap-2">
                                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search username or name" value="{{ old('q', $q ?? request('q')) }}">
                                <select name="rtom" class="form-select form-select-sm">
                                    <option value="">All RTOs</option>
                                    @foreach(($rtoms ?? collect()) as $r)
                                        <option value="{{ $r }}" {{ (string)($selectedRtom ?? request('rtom')) === (string)$r ? 'selected' : '' }}>{{ $r }}</option>
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
                                        <th>Assignment</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="rb-rtom-rows">
                                    @include('regionalbilling.region._rows', ['rtomAdmins' => $rtomAdmins])
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
