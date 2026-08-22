{{-- =============================================================================
     resources/views/admin/employees/index.blade.php
     -----------------------------------------------------------------------------
     Admin employee list with links to individual profiles.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'Employees')
@section('page_title', 'Employee Management')

@section('content')

<div class="stat-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0">
            <i class="bi bi-people me-2 text-primary"></i>All Employees
            <span class="badge bg-secondary ms-2" style="font-size:0.75rem;">{{ count($employees) }}</span>
        </h6>
    </div>

    @if(empty($employees))
        <p class="text-muted">No employees found.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Job Title</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $emp)
                        <tr>
                            <td class="text-muted">{{ $emp['user_id'] ?? '—' }}</td>
                            <td class="fw-semibold">{{ $emp['name'] ?? '—' }}</td>
                            <td class="text-muted">{{ $emp['email'] ?? '—' }}</td>
                            <td>{{ $emp['job_title'] ?? '—' }}</td>
                            <td>{{ $emp['department'] ?? '—' }}</td>
                            <td class="text-muted">{{ $emp['phone'] ?? '—' }}</td>
                            <td>
                                <a href="{{ route('admin.employees.show', $emp['user_id']) }}"
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
