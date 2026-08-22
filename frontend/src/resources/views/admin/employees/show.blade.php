{{-- =============================================================================
     resources/views/admin/employees/show.blade.php
     -----------------------------------------------------------------------------
     Admin: view and edit a single employee's profile.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'Edit Employee')
@section('page_title', 'Edit Employee Profile')

@section('content')

<div class="mb-3">
    <a href="{{ route('admin.employees') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Employees
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="stat-card">

            {{-- Profile header --}}
            <div class="d-flex align-items-center mb-4">
                <div style="width:56px;height:56px;border-radius:50%;background:#3b82d4;
                            display:flex;align-items:center;justify-content:center;
                            font-size:1.4rem;color:#fff;font-weight:700;flex-shrink:0;">
                    {{ strtoupper(substr($employee['name'] ?? 'U', 0, 1)) }}
                </div>
                <div class="ms-3">
                    <div class="fw-bold fs-5">{{ $employee['name'] ?? '—' }}</div>
                    <div class="text-muted" style="font-size:0.83rem;">{{ $employee['email'] ?? '' }}</div>
                    <span class="badge bg-secondary" style="font-size:0.72rem;">
                        ID #{{ $employee['user_id'] ?? $id }}
                    </span>
                </div>
            </div>

            {{-- Edit form --}}
            <form method="POST" action="{{ route('admin.employees.update', $id) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="phone">Phone</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror"
                           id="phone" name="phone"
                           value="{{ old('phone', $employee['phone'] ?? '') }}">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="address">Address</label>
                    <textarea class="form-control @error('address') is-invalid @enderror"
                              id="address" name="address" rows="2">{{ old('address', $employee['address'] ?? '') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="job_title">Job Title</label>
                    <input type="text" class="form-control @error('job_title') is-invalid @enderror"
                           id="job_title" name="job_title"
                           value="{{ old('job_title', $employee['job_title'] ?? '') }}">
                    @error('job_title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold" for="department">Department</label>
                    <input type="text" class="form-control @error('department') is-invalid @enderror"
                           id="department" name="department"
                           value="{{ old('department', $employee['department'] ?? '') }}">
                    @error('department')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
