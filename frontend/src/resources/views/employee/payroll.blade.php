{{-- =============================================================================
     resources/views/employee/payroll.blade.php
     -----------------------------------------------------------------------------
     Employee payroll: view payslip history (read-only).
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'My Payroll')
@section('page_title', 'My Payslips')

@section('content')

{{-- API error (backend unreachable or unexpected error) --}}
@if(!empty($apiError))
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Could not load payroll data: {{ $apiError }}
    </div>
@endif

@if(empty($payrolls))
    <div class="stat-card text-center py-5">
        <i class="bi bi-receipt fs-1 text-muted"></i>
        <p class="text-muted mt-3">No payroll records available yet. Contact HR.</p>
    </div>
@else

    {{-- Latest payslip highlight ──────────────────────────────────────────── --}}
    @php $latest = $payrolls[0]; @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-label">Basic Salary</div>
                <div class="stat-value" style="font-size:1.6rem;">
                    ₹{{ number_format($latest['basic_salary'] ?? 0, 2) }}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-label">Allowances</div>
                <div class="stat-value" style="font-size:1.6rem;color:#198754;">
                    +₹{{ number_format($latest['allowances'] ?? 0, 2) }}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-label">Deductions</div>
                <div class="stat-value" style="font-size:1.6rem;color:#dc3545;">
                    −₹{{ number_format($latest['deductions'] ?? 0, 2) }}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center" style="border-color:#3b82d4;">
                <div class="stat-label">Net Salary</div>
                <div class="stat-value" style="font-size:1.6rem;color:#3b82d4;">
                    ₹{{ number_format($latest['net_salary'] ?? 0, 2) }}
                </div>
                <div class="stat-label">
                    {{ \Carbon\Carbon::create()->month($latest['month'])->format('F') }}
                    {{ $latest['year'] }}
                </div>
            </div>
        </div>
    </div>

    {{-- All payslips table ─────────────────────────────────────────────────── --}}
    <div class="stat-card">
        <h6 class="fw-semibold mb-3">
            <i class="bi bi-table me-2"></i>Payroll History
        </h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th>Year</th>
                        <th>Basic Salary</th>
                        <th>Allowances</th>
                        <th>Deductions</th>
                        <th class="fw-bold">Net Salary</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payrolls as $payroll)
                        <tr>
                            <td>{{ \Carbon\Carbon::create()->month($payroll['month'])->format('F') }}</td>
                            <td>{{ $payroll['year'] }}</td>
                            <td>₹{{ number_format($payroll['basic_salary'] ?? 0, 2) }}</td>
                            <td class="text-success">+₹{{ number_format($payroll['allowances'] ?? 0, 2) }}</td>
                            <td class="text-danger">−₹{{ number_format($payroll['deductions'] ?? 0, 2) }}</td>
                            <td class="fw-bold">₹{{ number_format($payroll['net_salary'] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endif

@endsection
