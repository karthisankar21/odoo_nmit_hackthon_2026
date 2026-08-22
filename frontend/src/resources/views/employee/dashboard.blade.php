{{-- =============================================================================
     resources/views/employee/dashboard.blade.php
     -----------------------------------------------------------------------------
     Employee dashboard: today's attendance, pending leaves, latest payslip.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'My Dashboard')
@section('page_title', 'My Dashboard')

@section('content')

{{-- ── Stat cards row ─────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">

    {{-- Today's status --}}
    <div class="col-md-4">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-calendar-check fs-4 me-2 text-primary"></i>
                <span class="stat-label fw-semibold">Today's Status</span>
            </div>
            @if($todayRecord)
                <div class="stat-value" style="font-size:1.5rem;">
                    <span class="badge badge-{{ $todayRecord['status'] ?? 'absent' }} fs-6">
                        {{ ucfirst(str_replace('_', ' ', $todayRecord['status'] ?? 'absent')) }}
                    </span>
                </div>
                @if($todayRecord['check_in'])
                    <div class="text-muted mt-2" style="font-size:0.82rem;">
                        <i class="bi bi-box-arrow-in-right me-1"></i>In: {{ $todayRecord['check_in'] }}
                        @if($todayRecord['check_out'])
                            &nbsp;|&nbsp;<i class="bi bi-box-arrow-right me-1"></i>Out: {{ $todayRecord['check_out'] }}
                        @endif
                    </div>
                @endif
            @else
                <div class="stat-value" style="font-size:1.5rem;">
                    <span class="badge badge-absent fs-6">Not Checked In</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Pending leaves --}}
    <div class="col-md-4">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-calendar-x fs-4 me-2 text-warning"></i>
                <span class="stat-label fw-semibold">Pending Leaves</span>
            </div>
            <div class="stat-value">{{ $pendingLeaves }}</div>
            <div class="stat-label mt-1">awaiting approval</div>
        </div>
    </div>

    {{-- Latest net salary --}}
    <div class="col-md-4">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-cash-stack fs-4 me-2 text-success"></i>
                <span class="stat-label fw-semibold">Latest Net Salary</span>
            </div>
            @if($payroll)
                <div class="stat-value">₹{{ number_format($payroll['net_salary'] ?? 0, 2) }}</div>
                <div class="stat-label mt-1">
                    {{ \Carbon\Carbon::create()->month($payroll['month'])->format('F') }}
                    {{ $payroll['year'] }}
                </div>
            @else
                <div class="stat-value text-muted" style="font-size:1.2rem;">—</div>
                <div class="stat-label mt-1">No payroll record yet</div>
            @endif
        </div>
    </div>
</div>

{{-- ── Quick actions ───────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="stat-card">
            <h6 class="fw-semibold mb-3"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions</h6>
            <div class="d-flex gap-2 flex-wrap">
                {{-- Check In button --}}
                @if(!$checkedIn)
                    <form method="POST" action="{{ route('employee.checkin') }}" class="df-submit-form">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm df-submit-btn">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Check In
                        </button>
                    </form>
                @else
                    {{-- Check Out button (only if not yet checked out) --}}
                    @if(empty($todayRecord['check_out']))
                        <form method="POST" action="{{ route('employee.checkout') }}" class="df-submit-form">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm df-submit-btn">
                                <i class="bi bi-box-arrow-right me-1"></i>Check Out
                            </button>
                        </form>
                    @else
                        <button class="btn btn-secondary btn-sm" disabled>
                            <i class="bi bi-check2-circle me-1"></i>Attendance Done
                        </button>
                    @endif
                @endif

                <a href="{{ route('employee.leave') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Apply Leave
                </a>
                <a href="{{ route('employee.payroll') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-receipt me-1"></i>View Payslip
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ── Recent leave requests ───────────────────────────────────────────────── --}}
@if(!empty($recentLeaves))
<div class="row">
    <div class="col-12">
        <div class="stat-card">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-clock-history me-2"></i>Recent Leave Requests
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentLeaves as $leave)
                            <tr>
                                <td>{{ ucfirst($leave['leave_type'] ?? '—') }}</td>
                                <td>{{ $leave['start_date'] ?? '—' }}</td>
                                <td>{{ $leave['end_date'] ?? '—' }}</td>
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
            <div class="mt-2">
                <a href="{{ route('employee.leave') }}" class="btn btn-link btn-sm p-0">
                    View all leave requests →
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
