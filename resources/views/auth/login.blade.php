@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="content login-background d-flex">
    <div class="container login-overlay my-auto" style="padding: 40px 50px;">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex flex-column justify-content-center" style="padding: 30px;">
                <h1>Welcome</h1>
                <p class="mb-4">
                    This system streamlines your workflow, automates data validation, and applies business rules for fast, accurate results.
                </p>
            </div>
            <div class="col-md-4 d-flex flex-column justify-content-center">
                <div class="card shadow-sm border-0" style="padding: 20px;">
                    <div class="card-body login-card-body">
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