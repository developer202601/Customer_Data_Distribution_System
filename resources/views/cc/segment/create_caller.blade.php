@extends('layouts.cc')

@section('title', 'Add Caller')

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
                        <h1 class="process-upload-title mb-0">Add Caller</h1>
                    </div>
                    <a href="{{ route('cc.segment.callers') }}" class="btn btn-outline-secondary rounded-pill px-4">Back</a>
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

                <form method="post" action="{{ route('cc.segment.callers.store') }}">
                    @csrf
                    <div class="form-group mb-3">
                        <label for="username">Username <span class="text-danger">*</span></label>
                        <input id="username" name="username" type="text" class="form-control"
                            value="{{ old('username') }}" maxlength="6"
                            placeholder="6-digit staff ID" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="name">Name</label>
                        <input id="name" name="name" type="text" class="form-control"
                            value="{{ old('name') }}">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning rounded-pill px-4">Create Caller</button>
                        <a href="{{ route('cc.segment.callers') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
