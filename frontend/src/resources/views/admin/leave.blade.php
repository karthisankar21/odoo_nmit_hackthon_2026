{{-- =============================================================================
     resources/views/admin/leave.blade.php
     -----------------------------------------------------------------------------
     Admin: view all leave requests, approve or reject with one click.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'Leave Requests')
@section('page_title', 'Leave Approvals')

@section('content')

<div class="stat-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0">
            <i class="bi bi-calendar-x me-2 text-warning"></i>All Leave Requests
            <span class="badge bg-secondary ms-2" style="font-size:0.75rem;">{{ count($leaves) }}</span>
        </h6>
    </div>

    @if(empty($leaves))
        <p class="text-muted">No leave requests found.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaves as $leave)
                        <tr>
                            <td class="text-muted">{{ $leave['id'] ?? '—' }}</td>
                            <td class="fw-semibold">
                                {{ $leave['employee_name'] ?? 'ID #' . ($leave['employee_id'] ?? '?') }}
                            </td>
                            <td>{{ ucfirst($leave['leave_type'] ?? '—') }}</td>
                            <td>{{ $leave['start_date'] ?? '—' }}</td>
                            <td>{{ $leave['end_date'] ?? '—' }}</td>
                            <td class="text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $leave['reason'] ?? '—' }}
                            </td>
                            <td>
                                <span class="badge badge-{{ $leave['status'] ?? 'pending' }}">
                                    {{ ucfirst($leave['status'] ?? 'pending') }}
                                </span>
                            </td>
                            <td>
                                @if(($leave['status'] ?? '') === 'pending')
                                    {{-- Approve button --}}
                                    <form method="POST"
                                          action="{{ route('admin.leave.approve', $leave['id']) }}"
                                          class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    {{-- Reject button --}}
                                    <form method="POST"
                                          action="{{ route('admin.leave.reject', $leave['id']) }}"
                                          class="d-inline ms-1">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted" style="font-size:0.8rem;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
