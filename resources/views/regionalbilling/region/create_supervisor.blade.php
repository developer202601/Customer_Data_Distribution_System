@extends('layouts.cc')

@php use Illuminate\Support\Str; @endphp

@section('title', 'Create Caller')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Regional Billing — Create Caller</p>
                        <h1 class="process-upload-title mb-0">New Caller</h1>
                    </div>
                </div>

                <form action="{{ route('rb.region.store_supervisor') }}" method="post">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input name="username" type="text" class="form-control" maxlength="6" pattern="\d{6}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input name="name" type="text" class="form-control">
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-success">Create</button>
                        <a href="{{ route('rb.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
