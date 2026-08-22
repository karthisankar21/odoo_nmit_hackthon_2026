{{-- =============================================================================
     resources/views/employee/leave.blade.php
     -----------------------------------------------------------------------------
     Employee leave: apply for leave + history table.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'My Leave')
@section('page_title', 'Leave Management')

@section('content')

<div class="row g-4">

    {{-- ── Apply Leave form ────────────────────────────────────────────────── --}}
    <div class="col-md-5">
        <div class="stat-card h-100">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-plus-circle me-2 text-primary"></i>Apply for Leave
            </h6>

            <form method="POST" action="{{ route('employee.leave.apply') }}">
                @csrf

                {{-- Leave type --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="leave_type">Leave Type</label>
                    <select class="form-select @error('leave_type') is-invalid @enderror"
                            id="leave_type" name="leave_type">
                        <option value="">— Select —</option>
                        <option value="annual"  {{ old('leave_type') === 'annual'  ? 'selected' : '' }}>Annual</option>
                        <option value="sick"    {{ old('leave_type') === 'sick'    ? 'selected' : '' }}>Sick</option>
                        <option value="unpaid"  {{ old('leave_type') === 'unpaid'  ? 'selected' : '' }}>Unpaid</option>
                        <option value="other"   {{ old('leave_type') === 'other'   ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('leave_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Start date --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="start_date">From</label>
                    <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                           id="start_date" name="start_date"
                           value="{{ old('start_date', now()->toDateString()) }}"
                           min="{{ now()->toDateString() }}">
                    @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- End date --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="end_date">To</label>
                    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                           id="end_date" name="end_date"
                           value="{{ old('end_date', now()->toDateString()) }}"
                           min="{{ now()->toDateString() }}">
                    @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Reason --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="reason">Reason (optional)</label>
                    <textarea class="form-control @error('reason') is-invalid @enderror"
                              id="reason" name="reason" rows="3"
                              placeholder="Brief description…">{{ old('reason') }}</textarea>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100 df-submit-btn">
                    <i class="bi bi-send me-1"></i>Submit Application
                </button>
            </form>
        </div>
    </div>

    {{-- ── Leave history ────────────────────────────────────────────────────── --}}
    <div class="col-md-7">
        <div class="stat-card">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-clock-history me-2"></i>Leave History
            </h6>

            @if(empty($leaves))
                <p class="text-muted">No leave requests found.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leaves as $leave)
                                <tr>
                                    <td>{{ ucfirst($leave['leave_type'] ?? '—') }}</td>
                                    <td>{{ $leave['start_date'] ?? '—' }}</td>
                                    <td>{{ $leave['end_date'] ?? '—' }}</td>
                                    <td class="text-muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ $leave['reason'] ?? '—' }}
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $leave['status'] ?? 'pending' }}">
                                            {{ ucfirst($leave['status'] ?? 'pending') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>

@endsection
