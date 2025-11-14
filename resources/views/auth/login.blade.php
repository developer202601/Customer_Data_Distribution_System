@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="content login-background d-flex align-items-center justify-content-center">
    <div class="container login-overlay my-auto p-4 p-md-5">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-md-8 d-flex flex-column justify-content-center p-3 p-md-4">
                <h1>Welcome</h1>
                <p class="mb-4">
                    This system streamlines your workflow, automates data validation, and applies business rules for fast, accurate results.
                </p>
            </div>
            <div class="col-md-4 d-flex flex-column justify-content-center">
                <div class="card shadow-sm border-0">
                    <div class="card-body login-card-body p-4">
                        <p class="login-box-msg h5 mb-4">Login</p>

                        <form action="{{ route('login.perform') }}" method="post">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" id="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="Enter 6-digit username" maxlength="6">
                                @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection