{{-- =============================================================================
     resources/views/employee/profile.blade.php
     -----------------------------------------------------------------------------
     Employee profile: view and edit personal details.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'My Profile')
@section('page_title', 'My Profile')

@section('content')

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="stat-card">

            {{-- ── Profile header ──────────────────────────────────────────── --}}
            <div class="d-flex align-items-center mb-4">
                <div style="width:60px;height:60px;border-radius:50%;background:#3b82d4;
                            display:flex;align-items:center;justify-content:center;
                            font-size:1.5rem;color:#fff;font-weight:700;flex-shrink:0;">
                    {{ strtoupper(substr(session('name', 'U'), 0, 1)) }}
                </div>
                <div class="ms-3">
                    <div class="fw-bold fs-5">{{ session('name', '—') }}</div>
                    <div class="text-muted" style="font-size:0.85rem;">
                        {{ $employee['job_title'] ?? 'Employee' }}
                        @if(!empty($employee['department']))
                            &nbsp;·&nbsp;{{ $employee['department'] }}
                        @endif
                    </div>
                    <div class="text-muted" style="font-size:0.82rem;">{{ session('email', '') }}</div>
                </div>
            </div>

            {{-- ── Edit form ────────────────────────────────────────────────── --}}
            <form method="POST" action="{{ route('employee.profile.update') }}">
                @csrf

                {{-- Phone --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="phone">Phone</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror"
                           id="phone" name="phone"
                           value="{{ old('phone', $employee['phone'] ?? '') }}"
                           placeholder="+91 9000000000">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Address --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="address">Address</label>
                    <textarea class="form-control @error('address') is-invalid @enderror"
                              id="address" name="address" rows="2"
                              placeholder="Street, City, State">{{ old('address', $employee['address'] ?? '') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Job Title --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="job_title">Job Title</label>
                    <input type="text" class="form-control @error('job_title') is-invalid @enderror"
                           id="job_title" name="job_title"
                           value="{{ old('job_title', $employee['job_title'] ?? '') }}"
                           placeholder="Software Engineer">
                    @error('job_title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Department --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="department">Department</label>
                    <input type="text" class="form-control @error('department') is-invalid @enderror"
                           id="department" name="department"
                           value="{{ old('department', $employee['department'] ?? '') }}"
                           placeholder="Engineering">
                    @error('department')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary df-submit-btn">
                        <i class="bi bi-floppy me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
