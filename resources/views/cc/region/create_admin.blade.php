@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Create RTOM Admin</p>
                        <h1 class="process-upload-title mb-0">New RTOM Admin</h1>
                    </div>
                </div>

                <form action="{{ route('cc.region.store_admin') }}" method="post">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input name="username" type="text" class="form-control" maxlength="6" pattern="\d{6}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input name="name" type="text" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">RTOM</label>
                        <select name="rtom" class="form-select">
                            @foreach($rtoms as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-success">Create</button>
                        <a href="{{ route('cc.region.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
