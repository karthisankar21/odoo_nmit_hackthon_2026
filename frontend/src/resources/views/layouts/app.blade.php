<!DOCTYPE html>
{{-- =============================================================================
     resources/views/layouts/app.blade.php
     -----------------------------------------------------------------------------
     Main application layout with sidebar + topbar.
     Used by all employee and admin pages.
     ============================================================================= --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dayflow HRMS — @yield('title', 'Dashboard')</title>

    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        /* ── Layout ──────────────────────────────────────────────────────────── */
        body { background: #f0f2f5; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #1f2328;
            position: fixed;
            top: 0; left: 0;
            padding-top: 1rem;
            z-index: 100;
        }
        .sidebar .brand {
            color: #ffffff;
            font-size: 1.2rem;
            font-weight: 700;
            padding: 0.75rem 1.25rem 1.5rem;
            border-bottom: 1px solid #30363d;
            letter-spacing: -0.3px;
        }
        .sidebar .brand small {
            display: block;
            font-size: 0.7rem;
            color: #8b949e;
            font-weight: 400;
            margin-top: 2px;
        }
        .sidebar .nav-link {
            color: #c9d1d9;
            padding: 0.6rem 1.25rem;
            border-radius: 6px;
            margin: 2px 8px;
            font-size: 0.9rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #30363d;
            color: #ffffff;
        }
        .sidebar .nav-link i { margin-right: 8px; width: 16px; }

        .main-content {
            margin-left: 240px;
            min-height: 100vh;
        }

        /* ── Topbar ──────────────────────────────────────────────────────────── */
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar .page-title { font-size: 1.05rem; font-weight: 600; color: #1f2328; }
        .topbar .user-info  { font-size: 0.85rem; color: #57606a; }

        /* ── Page body ───────────────────────────────────────────────────────── */
        .page-body { padding: 1.5rem; }

        /* ── Status badges ───────────────────────────────────────────────────── */
        .badge-present  { background-color: #198754; color: #fff; }
        .badge-absent   { background-color: #dc3545; color: #fff; }
        .badge-leave    { background-color: #0d6efd; color: #fff; }
        .badge-half-day { background-color: #fd7e14; color: #fff; }
        .badge-pending  { background-color: #ffc107; color: #212529; }
        .badge-approved { background-color: #198754; color: #fff; }
        .badge-rejected { background-color: #dc3545; color: #fff; }

        /* ── Cards ───────────────────────────────────────────────────────────── */
        .stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            padding: 1.25rem;
        }
        .stat-card .stat-value { font-size: 2rem; font-weight: 700; color: #1f2328; }
        .stat-card .stat-label { font-size: 0.8rem; color: #57606a; margin-top: 2px; }
    </style>
</head>
<body>

{{-- ── Sidebar ──────────────────────────────────────────────────────────────── --}}
<nav class="sidebar">
    <div class="brand">
        Dayflow
        <small>HR Management System</small>
    </div>

    <ul class="nav flex-column mt-2">
        @if(session('role') === 'admin')
            {{-- Admin navigation --}}
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}"
                   class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.employees') }}"
                   class="nav-link {{ request()->routeIs('admin.employees*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Employees
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.attendance') }}"
                   class="nav-link {{ request()->routeIs('admin.attendance*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i> Attendance
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.leave') }}"
                   class="nav-link {{ request()->routeIs('admin.leave*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-x"></i> Leave Requests
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.payroll') }}"
                   class="nav-link {{ request()->routeIs('admin.payroll*') ? 'active' : '' }}">
                    <i class="bi bi-cash-stack"></i> Payroll
                </a>
            </li>
        @else
            {{-- Employee navigation --}}
            <li class="nav-item">
                <a href="{{ route('employee.dashboard') }}"
                   class="nav-link {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.profile') }}"
                   class="nav-link {{ request()->routeIs('employee.profile*') ? 'active' : '' }}">
                    <i class="bi bi-person-circle"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.attendance') }}"
                   class="nav-link {{ request()->routeIs('employee.attendance*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i> Attendance
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.leave') }}"
                   class="nav-link {{ request()->routeIs('employee.leave*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-x"></i> Leave
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.payroll') }}"
                   class="nav-link {{ request()->routeIs('employee.payroll*') ? 'active' : '' }}">
                    <i class="bi bi-cash-stack"></i> Payroll
                </a>
            </li>
        @endif
    </ul>

    {{-- Logout at bottom --}}
    <div style="position: absolute; bottom: 1.5rem; left: 0; right: 0; padding: 0 8px;">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link btn btn-link w-100 text-start" style="color:#c9d1d9;">
                <i class="bi bi-box-arrow-left"></i> Logout
            </button>
        </form>
    </div>
</nav>

{{-- ── Main content area ────────────────────────────────────────────────────── --}}
<div class="main-content">

    {{-- Topbar --}}
    <div class="topbar">
        <span class="page-title">@yield('page_title', 'Dashboard')</span>
        <span class="user-info">
            <i class="bi bi-person-fill me-1"></i>
            {{ session('name', 'User') }}
            <span class="badge bg-secondary ms-1" style="font-size:0.7rem;">
                {{ ucfirst(session('role', '')) }}
            </span>
        </span>
    </div>

    {{-- Page body --}}
    <div class="page-body">

        {{-- Global error alert (passed from controller via session) --}}
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Global success alert --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>
</div>

{{-- Bootstrap 5 JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- ── Submit spinner: disables button and shows spinner on any form submit ── --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.df-submit-btn').forEach(function (btn) {
        btn.closest('form').addEventListener('submit', function () {
            btn.disabled = true;
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'spinner-border spinner-border-sm me-1';
                icon.setAttribute('role', 'status');
            }
        });
    });
});
</script>

@stack('scripts')
</body>
</html>
