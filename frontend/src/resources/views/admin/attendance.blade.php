{{-- =============================================================================
     resources/views/admin/attendance.blade.php
     -----------------------------------------------------------------------------
     Admin: all attendance records across all employees.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'Attendance')
@section('page_title', 'Attendance Records')

@section('content')

<div class="stat-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0">
            <i class="bi bi-calendar-check me-2 text-primary"></i>All Attendance Records
            <span class="badge bg-secondary ms-2" style="font-size:0.75rem;">{{ count($records) }}</span>
        </h6>
    </div>

    @if(empty($records))
        <p class="text-muted">No attendance records found.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        <tr>
                            <td class="fw-semibold">{{ $record['employee_name'] ?? 'ID #' . ($record['employee_id'] ?? '?') }}</td>
                            <td>{{ $record['date'] ?? '—' }}</td>
                            <td>{{ $record['check_in'] ?? '—' }}</td>
                            <td>{{ $record['check_out'] ?? '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $record['status'] ?? 'absent' }}">
                                    {{ ucfirst(str_replace('_', ' ', $record['status'] ?? 'absent')) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
