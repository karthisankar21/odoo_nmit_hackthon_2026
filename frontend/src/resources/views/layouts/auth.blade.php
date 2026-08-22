<!DOCTYPE html>
{{-- =============================================================================
     resources/views/layouts/auth.blade.php
     -----------------------------------------------------------------------------
     Minimal centered-card layout used only for the login page.
     Uses Bootstrap 5 CDN for styling.
     ============================================================================= --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dayflow HRMS — @yield('title', 'Login')</title>

    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
            padding: 2.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .brand-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1f2328;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            color: #57606a;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        {{-- Brand header --}}
        <div class="text-center mb-4">
            <div class="brand-title">Dayflow</div>
            <div class="brand-sub">Human Resource Management System</div>
        </div>

        {{-- Page content --}}
        @yield('content')
    </div>

    {{-- Bootstrap 5 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
