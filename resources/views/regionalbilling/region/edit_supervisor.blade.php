@extends('layouts.cc')

@section('title', 'Edit Supervisor')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Edit Supervisor</p>
                        <h1 class="process-upload-title mb-0">{{ $user->username }}</h1>
                    </div>
                </div>

                <form action="{{ route('rb.region.update_supervisor', $user) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supervisor Assignment</label>
                        <input type="text" class="form-control" value="{{ $user->assignment }}" disabled>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Save</button>
                        <a href="{{ route('rb.supervisor.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection