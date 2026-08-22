{{-- =============================================================================
     resources/views/auth/register.blade.php
     -----------------------------------------------------------------------------
     Registration page — creates a new Dayflow HRMS account.
     Auto-logs in on success and redirects to the role-appropriate dashboard.
     ============================================================================= --}}
@extends('layouts.auth')

@section('title', 'Create Account')

@section('content')

<h5 class="fw-semibold mb-1" style="font-size:1rem;">Create your account</h5>
<p class="text-muted mb-4" style="font-size:0.82rem;">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>

{{-- Error alert --}}
@if(session('error'))
    <div class="alert alert-danger py-2" style="font-size:0.85rem;">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ session('error') }}
    </div>
@endif

<form method="POST" action="{{ route('register.submit') }}">
    @csrf

    {{-- Full name --}}
    <div class="mb-3">
        <label class="form-label fw-semibold" for="name" style="font-size:0.85rem;">Full Name</label>
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name" name="name"
               value="{{ old('name') }}"
               placeholder="Alice Smith"
               autofocus required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Email --}}
    <div class="mb-3">
        <label class="form-label fw-semibold" for="email" style="font-size:0.85rem;">Email address</label>
        <input type="email"
               class="form-control @error('email') is-invalid @enderror"
               id="email" name="email"
               value="{{ old('email') }}"
               placeholder="you@company.com"
               required>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Password --}}
    <div class="mb-3">
        <label class="form-label fw-semibold" for="password" style="font-size:0.85rem;">Password</label>
        <input type="password"
               class="form-control @error('password') is-invalid @enderror"
               id="password" name="password"
               placeholder="At least 6 characters"
               required>
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Role --}}
    <div class="mb-4">
        <label class="form-label fw-semibold" for="role" style="font-size:0.85rem;">Role</label>
        <select class="form-select @error('role') is-invalid @enderror"
                id="role" name="role" required>
            <option value="employee" {{ old('role', 'employee') === 'employee' ? 'selected' : '' }}>Employee</option>
            <option value="admin"    {{ old('role') === 'admin'                ? 'selected' : '' }}>Admin / HR</option>
        </select>
        @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary w-100 df-submit-btn">
        <i class="bi bi-person-plus me-1"></i>Create Account
    </button>
</form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('.df-submit-btn');
    if (btn) {
        btn.closest('form').addEventListener('submit', function () {
            btn.disabled = true;
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'spinner-border spinner-border-sm me-1';
                icon.setAttribute('role', 'status');
            }
        });
    }
});
</script>
@endpush
