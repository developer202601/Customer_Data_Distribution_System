@extends('layouts.guest')

@section('title', 'CDDS & PRMS')

@section('content')
<section class="content login-page">
    <div class="login-page__panel login-page__panel--visual">
        <div class="login-page__visual-inner">
            <div class="login-page__visual-badges">
                <span class="login-page__pill">Real-time coordination</span>
                <span class="login-page__pill login-page__pill--accent">24/7 visibility</span>
            </div>
            <span class="login-page__eyebrow">Customer Data Distribution System</span>
            <h1>Secure access to your reporting workspace</h1>
            <p>Sign in to coordinate data delivery, validate submissions, and keep your distribution workflows on track.</p>
            <ul class="login-page__highlights">
                <li>Fast access to the dashboard</li>
                <li>Secure Microsoft sign-in support</li>
                <li>Built for call center and reporting operations</li>
            </ul>
        </div>
    </div>

    <div class="login-page__panel login-page__panel--form">
        <div class="login-card shadow-sm">
            <div class="login-card__header">
                <span class="login-card__badge">Secure access</span>
                <h2>Welcome back</h2>
                <p>Enter your username to continue to the platform.</p>
            </div>

            <form action="{{ route('login.perform') }}" method="post">
                @csrf
                <div class="form-group mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group login-card__input-group">
                        <span class="input-group-text" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input type="text" name="username" id="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="Enter 6-digit username" maxlength="6" autocomplete="username" autocapitalize="none">
                    </div>
                    @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block w-100 login-card__submit">Continue</button>

                <div class="login-card__divider"><span>or</span></div>

                <a href="/auth/microsoft" class="login-card__secondary">
                    <svg viewBox="0 0 23 23" width="20" height="20" class="shrink-0">
                        <path fill="#f35325" d="M0 0h11v11H0z"></path>
                        <path fill="#81bc06" d="M12 0h11v11H12z"></path>
                        <path fill="#05a6f0" d="M0 12h11v11H0z"></path>
                        <path fill="#ffba08" d="M12 12h11v11H12z"></path>
                    </svg>
                    <span>Sign in with Microsoft</span>
                </a>
            </form>

            <div class="login-card__footer">
                <span>Powered by</span>
                <a href="" aria-label="Transzent">
                    <img src="{{ asset('images/Transzent-logo.png') }}" alt="Transzent" />
                </a>
            </div>
        </div>
    </div>
</section>
@endsection