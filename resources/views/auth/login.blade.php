@extends('layouts.guest')

@section('title', 'CDDS & PRMS')

@section('content')
<section class="content login-page">
    <div class="login-page__panel login-page__panel--visual">
        <div class="login-page__visual-inner">
            <span class="login-page__eyebrow">Customer Data Distribution & Payment Reminder System</span>
            <h1>Secure delivery for your customers</h1>
            <p>
                Customer data distribution system that keeps every client package verified, tracked, and compliant.
            </p>
            <p>
                Payment reminder management system that provides automated payment reminders, overdue tracking, and financial performance oversight across the organization.
            </p>
            <p class="login-page__visual-note">Trusted dashboards, concise alerts, zero guesswork.</p>
        </div>
    </div>
    <div class="login-page__panel login-page__panel--form">
        <div class="login-card shadow-sm">
            <div class="login-card__header">
                <p class="text-uppercase mb-1">Secure Access</p>
                <h2>Customer Portal Login</h2>
                <p class="text-muted">Access your distribution feeds, delivery approvals, and payment management tools.</p>
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
                <br/>
                <div align="center">
                    <span class="text-muted p-3">Or</span>
                <br/>
                <a href="/auth/microsoft" class="btn btn-primary d-inline-flex align-items-center justify-content-center px-5"><svg viewBox="0 0 23 23" width="20" height="20" class="shrink-0"><path fill="#f35325" d="M0 0h11v11H0z"></path><path fill="#81bc06" d="M12 0h11v11H12z"></path><path fill="#05a6f0" d="M0 12h11v11H0z"></path><path fill="#ffba08" d="M12 12h11v11H12z"></path></svg><span class="ms-2">Sign in with Microsoft</span></a>
                </div>
                <!-- <p class="login-card__hint mt-3">Having trouble? <a href="#">Contact support</a></p> -->
            </form>
            <div class="text-center mt-3" style="display:inline-flex;align-items:center;gap:.5rem;white-space:nowrap;">
                <span>Powered by</span>
                <a href="" style="display:inline-flex;align-items:center;gap:.25rem;">
                    <img src="{{ asset('images/Transzent-logo.png') }}" alt="Transzent" style="height:24px;max-height:24px;padding-bottom:1px;" />
                </a>
            </div>
        </div>
    </div>
</section>
@endsection