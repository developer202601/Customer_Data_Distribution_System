@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Edit RTOM Admin</p>
                        <h1 class="process-upload-title mb-0">{{ $user->username }}</h1>
                    </div>
                </div>

                <form action="{{ route('cc.region.update_admin', $user) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">RTOM</label>
                        <select name="rtom" class="form-select">
                            @foreach($rtoms as $r)
                                <option value="{{ $r }}" {{ (old('rtom') ?: str_replace('rtom_','', $user->assignment)) === $r ? 'selected' : '' }}>{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Save</button>
                        <a href="{{ route('cc.region.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>

                <form action="{{ route('cc.region.destroy_admin', $user) }}" method="post" class="mt-3" onsubmit="return confirm('Delete this RTOM admin?');">
                    @csrf
                    @method('delete')
                    <button class="btn btn-danger" type="submit">Delete</button>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
