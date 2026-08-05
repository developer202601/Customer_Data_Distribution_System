@extends('layouts.cc')

@section('title', 'Create Segment Admin')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Create Segment Admin</h1>
                        <p class="text-muted small mb-0">Creates a new segment admin for one of the three CC segments.</p>
                    </div>
                    <a href="{{ route('cc.super.segments') }}" class="btn btn-outline-secondary rounded-pill px-4">Back</a>
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

                <form method="post" action="{{ route('cc.super.store_user') }}">
                    @csrf

                    <div class="form-group mb-3">
                        <label for="username">Username <span class="text-danger">*</span></label>
                        <input id="username" name="username" type="text" class="form-control"
                            value="{{ old('username') }}" maxlength="6"
                            placeholder="6-digit staff ID" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="name">Name</label>
                        <input id="name" name="name" type="text" class="form-control"
                            value="{{ old('name') }}">
                    </div>

                    <div class="form-group mb-4">
                        <label for="segment">Segment <span class="text-danger">*</span></label>
                        <select id="segment" name="segment" class="form-select" required>
                            <option value="">— Select segment —</option>
                            @foreach($segments as $key => $label)
                                <option value="{{ $key }}" {{ old('segment') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning rounded-pill px-4">Create</button>
                        <a href="{{ route('cc.super.segments') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
