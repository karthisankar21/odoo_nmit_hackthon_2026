{{-- =============================================================================
     resources/views/admin/payroll.blade.php
     -----------------------------------------------------------------------------
     Admin payroll: view all records + inline upsert form per employee.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'Payroll')
@section('page_title', 'Payroll Management')

@section('content')

{{-- ── Payroll records table ───────────────────────────────────────────────── --}}
{{-- NOTE: $employees is passed from the controller for the payroll form dropdown --}}
<div class="stat-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0">
            <i class="bi bi-cash-stack me-2 text-success"></i>All Payroll Records
            <span class="badge bg-secondary ms-2" style="font-size:0.75rem;">{{ count($payrolls) }}</span>
        </h6>
    </div>

    @if(empty($payrolls))
        <p class="text-muted">No payroll records found.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
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
                            <td class="fw-semibold">
                                {{ $payroll['employee_name'] ?? 'ID #' . ($payroll['employee_id'] ?? '?') }}
                            </td>
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
    @endif
</div>

{{-- ── Add / Update payroll form ───────────────────────────────────────────── --}}
<div class="stat-card">
    <h6 class="fw-semibold mb-3">
        <i class="bi bi-pencil-square me-2 text-primary"></i>Add / Update Payroll Record
    </h6>
    <p class="text-muted mb-3" style="font-size:0.85rem;">
        Enter the employee's user ID and salary components. Net salary is calculated automatically by the server.
    </p>

    <form method="POST" action="" id="payrollForm">
        @csrf

        <div class="row g-3">
            {{-- Employee — dropdown so admin doesn't have to guess the ID --}}
            <div class="col-md-3">
                <label class="form-label fw-semibold" for="employee_id">Employee</label>
                <select class="form-select" id="employee_id" name="employee_id" required>
                    <option value="">— Select Employee —</option>
                    @foreach($employees ?? [] as $emp)
                        <option value="{{ $emp['id'] }}">
                            #{{ $emp['id'] }} — {{ $emp['name'] ?? $emp['email'] ?? 'Unknown' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Basic Salary --}}
            <div class="col-md-2">
                <label class="form-label fw-semibold" for="basic_salary">Basic Salary</label>
                <input type="number" step="0.01" class="form-control @error('basic_salary') is-invalid @enderror"
                       id="basic_salary" name="basic_salary"
                       value="{{ old('basic_salary', '') }}"
                       placeholder="50000.00" min="0" required>
                @error('basic_salary')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Allowances --}}
            <div class="col-md-2">
                <label class="form-label fw-semibold" for="allowances">Allowances</label>
                <input type="number" step="0.01" class="form-control @error('allowances') is-invalid @enderror"
                       id="allowances" name="allowances"
                       value="{{ old('allowances', '') }}"
                       placeholder="5000.00" min="0" required>
                @error('allowances')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Deductions --}}
            <div class="col-md-2">
                <label class="form-label fw-semibold" for="deductions">Deductions</label>
                <input type="number" step="0.01" class="form-control @error('deductions') is-invalid @enderror"
                       id="deductions" name="deductions"
                       value="{{ old('deductions', '') }}"
                       placeholder="2000.00" min="0" required>
                @error('deductions')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Month --}}
            <div class="col-md-2">
                <label class="form-label fw-semibold" for="month">Month</label>
                <select class="form-select @error('month') is-invalid @enderror" id="month" name="month" required>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ (old('month', now()->month) == $m) ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                        </option>
                    @endfor
                </select>
                @error('month')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Year --}}
            <div class="col-md-2">
                <label class="form-label fw-semibold" for="year">Year</label>
                <input type="number" class="form-control @error('year') is-invalid @enderror"
                       id="year" name="year"
                       value="{{ old('year', now()->year) }}"
                       min="2000" max="2100" required>
                @error('year')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary" id="payrollSubmit">
                <i class="bi bi-floppy me-1"></i>Save Payroll
            </button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
    // Dynamically set the form action based on the entered employee ID
    document.getElementById('payrollSubmit').addEventListener('click', function(e) {
        var empId = document.getElementById('employee_id').value;
        if (!empId) { return; }
        var form = document.getElementById('payrollForm');
        form.action = '/admin/payroll/' + encodeURIComponent(empId);
    });
</script>
@endpush
