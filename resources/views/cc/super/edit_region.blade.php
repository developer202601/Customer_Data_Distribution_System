@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Edit Region Admin</p>
                        <h1 class="process-upload-title mb-0">{{ $user->username }}</h1>
                    </div>
                </div>

                <form action="{{ route('cc.super.update_region', $user) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Region</label>
                        <select name="region" class="form-select" {{ $user->fixed ? 'disabled' : '' }}>
                            @foreach($regions as $r)
                                <option value="{{ $r }}" {{ (old('region') ?: $user->assignment) === $r ? 'selected' : '' }}>{{ $r }}</option>
                            @endforeach
                        </select>
                        @if($user->fixed)
                            <input type="hidden" name="region" value="{{ old('region', $user->assignment) }}">
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Save</button>
                        <a href="{{ route('cc.super.regions') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>

                {{-- Delete action removed: deletion handled from region list only --}}

            </div>
        </div>
    </div>
</div>
@endsection