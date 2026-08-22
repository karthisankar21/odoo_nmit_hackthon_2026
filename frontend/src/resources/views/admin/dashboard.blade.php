{{-- =============================================================================
     resources/views/admin/dashboard.blade.php
     -----------------------------------------------------------------------------
     Admin analytics dashboard: headcount, today's attendance, leave summary,
     department breakdown.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page_title', 'Analytics Dashboard')

@section('content')

{{-- ── Row 1: key metrics ──────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    {{-- Total employees --}}
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-label">Total Employees</div>
            <div class="stat-value">{{ $summary['total_employees'] ?? 0 }}</div>
        </div>
    </div>

    {{-- Today present --}}
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-label">Present Today</div>
            <div class="stat-value" style="color:#198754;">
                {{ $summary['today_attendance']['present'] ?? 0 }}
            </div>
        </div>
    </div>

    {{-- Today absent --}}
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-label">Absent Today</div>
            <div class="stat-value" style="color:#dc3545;">
                {{ $summary['today_attendance']['absent'] ?? 0 }}
            </div>
        </div>
    </div>

    {{-- Pending leave requests --}}
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-label">Pending Leaves</div>
            <div class="stat-value" style="color:#ffc107;">
                {{ $summary['pending_leaves'] ?? 0 }}
            </div>
        </div>
    </div>
</div>

{{-- ── Row 2: today's attendance breakdown + leave by type ───────────────── --}}
<div class="row g-3 mb-4">

    {{-- Today's attendance breakdown --}}
    <div class="col-md-6">
        <div class="stat-card h-100">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-calendar-check me-2 text-primary"></i>Today's Attendance Breakdown
            </h6>
            @php
                $att = $summary['today_attendance'] ?? [];
                $total = max($att['total'] ?? 1, 1);
            @endphp
            @foreach([
                ['present',  'Present',  'badge-present',  '#198754'],
                ['absent',   'Absent',   'badge-absent',   '#dc3545'],
                ['leave',    'On Leave', 'badge-leave',    '#0d6efd'],
                ['half_day', 'Half Day', 'badge-half-day', '#fd7e14'],
            ] as [$key, $label, $badge, $color])
                <div class="d-flex align-items-center mb-2">
                    <div style="width:90px;font-size:0.82rem;" class="text-muted">{{ $label }}</div>
                    <div class="progress flex-grow-1 me-2" style="height:10px;border-radius:6px;">
                        <div class="progress-bar" role="progressbar"
                             style="width:{{ round(($att[$key] ?? 0) / $total * 100) }}%;background:{{ $color }};">
                        </div>
                    </div>
                    <span class="badge {{ $badge }}" style="min-width:28px;">{{ $att[$key] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Leave by type --}}
    <div class="col-md-6">
        <div class="stat-card h-100">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-pie-chart me-2 text-warning"></i>Leave by Type (All Time)
            </h6>
            @if(empty($leaveTypeData))
                <p class="text-muted">No leave data available.</p>
            @else
                @foreach($leaveTypeData as $type => $count)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-capitalize" style="font-size:0.88rem;">{{ str_replace('_', ' ', $type) }}</span>
                        <span class="badge bg-secondary">{{ $count }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

{{-- ── Row 3: department headcount ────────────────────────────────────────── --}}
@if(!empty($deptData))
<div class="row">
    <div class="col-12">
        <div class="stat-card">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-diagram-3 me-2 text-primary"></i>Headcount by Department
            </h6>
            <div class="row g-3">
                @foreach($deptData as $dept)
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 rounded" style="background:#f7f8fa;border:1px solid #e5e7eb;text-align:center;">
                            <div style="font-size:1.8rem;font-weight:700;color:#3b82d4;">{{ $dept['value'] }}</div>
                            <div style="font-size:0.78rem;color:#57606a;margin-top:2px;">{{ $dept['label'] ?: 'Unassigned' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Quick links ─────────────────────────────────────────────────────────── --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.employees') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-people me-1"></i>Manage Employees
            </a>
            <a href="{{ route('admin.leave') }}" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-calendar-x me-1"></i>Review Leave Requests
            </a>
            <a href="{{ route('admin.payroll') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-cash-stack me-1"></i>Manage Payroll
            </a>
            <a href="{{ route('admin.attendance') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-calendar-check me-1"></i>View Attendance
            </a>
        </div>
    </div>
</div>

@endsection
