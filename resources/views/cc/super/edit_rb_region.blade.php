@extends('layouts.cc')

@section('title', 'Edit RB Region Admin')

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
            <div class="card-body p-4 p-lg-5" style="max-width: 540px;">

                <div class="mb-4">
                    <p class="text-uppercase text-muted mb-1">Edit RB Region Admin</p>
                    <h1 class="process-upload-title mb-0">{{ $user->username }}</h1>
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

                <form action="{{ route('cc.super.rb_regions.update', $user) }}" method="post">
                    @csrf
                    @method('put')

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="form-control-plaintext"><strong>{{ $user->username }}</strong></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Region</label>
                        <div class="form-control-plaintext"><strong>{{ $user->assignment ?? '—' }}</strong></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="name">Display Name</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" maxlength="45" placeholder="Optional display name">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Save</button>
                        <a href="{{ route('cc.super.rb_regions') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
