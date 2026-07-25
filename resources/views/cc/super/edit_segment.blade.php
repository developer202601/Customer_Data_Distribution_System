@extends('layouts.cc')

@section('title', 'Edit Segment Admin')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Edit Segment Admin: {{ $user->username }}</h1>
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

                <form method="post" action="{{ route('cc.super.update_segment', $user) }}">
                    @csrf @method('PUT')

                    <div class="form-group mb-3">
                        <label>Username</label>
                        <input type="text" class="form-control" value="{{ $user->username }}" disabled>
                    </div>

                    <div class="form-group mb-3">
                        <label for="name">Name</label>
                        <input id="name" name="name" type="text" class="form-control"
                            value="{{ old('name', $user->name) }}">
                    </div>

                    <div class="form-group mb-4">
                        <label for="segment">Segment <span class="text-danger">*</span></label>
                        <select id="segment" name="segment" class="form-select" required
                            {{ $user->fixed ? 'disabled' : '' }}>
                            @foreach($segments as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('segment', $user->assignment) === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @if($user->fixed)
                            {{-- Submit the current value since the select is disabled --}}
                            <input type="hidden" name="segment" value="{{ $user->assignment }}">
                            <div class="form-text text-muted">Segment is locked for this user.</div>
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning rounded-pill px-4">Save</button>
                        <a href="{{ route('cc.super.segments') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
