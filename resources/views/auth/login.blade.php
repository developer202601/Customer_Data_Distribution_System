@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<section class="content login-page">
    <div class="login-page__panel login-page__panel--visual" style="background-image: url('/images/login-network.svg'); background-size: cover; background-position: center;">
        <div class="login-page__visual-inner">
            <span class="login-page__eyebrow">Customer Data Distribution System</span>
            <h1>Secure delivery for your customers</h1>
            <p>
                This is the customer data distribution system that keeps every client package verified, tracked, and compliant.
            </p>
            <p class="login-page__visual-note">Trusted dashboards, concise alerts, zero guesswork.</p>
        </div>
    </div>
    <div class="login-page__panel login-page__panel--form">
        <div class="login-card shadow-sm">
            <div class="login-card__header">
                <p class="text-uppercase mb-1">Secure Access</p>
                <h2>Customer Portal Login</h2>
                <p class="text-muted">Access your distribution feeds and delivery approvals.</p>
            </div>
            <form action="{{ route('login.perform') }}" method="post">
                @csrf
                <div class="form-group mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="Enter 6-digit username" maxlength="6">
                    @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-block w-100">Continue</button>
                <p class="login-card__hint mt-3">Having trouble? <a href="#">Contact support</a></p>
            </form>
        </div>
    </div>
</section>
@endsection