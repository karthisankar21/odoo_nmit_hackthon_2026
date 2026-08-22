{{-- =============================================================================
     resources/views/auth/login.blade.php
     -----------------------------------------------------------------------------
     Login form for Dayflow HRMS.
     Extends the minimal auth.blade.php layout.
     ============================================================================= --}}
@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')

    {{-- ── Inline error message from controller ────────────────────────────── --}}
    {{-- Shown when backend returns 401 (wrong password) or 403 (disabled account) --}}
    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- ── Login form ──────────────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
        @csrf

        {{-- Email field --}}
        <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Email address</label>
            <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="you@dayflow.com"
                required
                autofocus
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Password field --}}
        <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Password</label>
            <input
                type="password"
                class="form-control @error('password') is-invalid @enderror"
                id="password"
                name="password"
                placeholder="••••••••"
                required
            >
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Submit button — shows spinner while submitting --}}
        <button
            type="submit"
            class="btn btn-dark w-100"
            id="submitBtn"
        >
            <span id="btnText">Sign In</span>
            <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
        </button>

    </form>

@endsection

@push('scripts')
<script>
    // Show loading spinner on form submit — prevents double-click
    document.getElementById('loginForm').addEventListener('submit', function () {
        const btn     = document.getElementById('submitBtn');
        const text    = document.getElementById('btnText');
        const spinner = document.getElementById('btnSpinner');

        btn.disabled = true;
        text.textContent = 'Signing in…';
        spinner.classList.remove('d-none');
    });
</script>
@endpush
