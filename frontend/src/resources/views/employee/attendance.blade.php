{{-- =============================================================================
     resources/views/employee/attendance.blade.php
     -----------------------------------------------------------------------------
     Employee attendance: check-in / check-out buttons + history table.
     ============================================================================= --}}
@extends('layouts.app')

@section('title', 'My Attendance')
@section('page_title', 'My Attendance')

@section('content')

{{-- ── Today's action card ─────────────────────────────────────────────────── --}}
<div class="row mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-calendar-day me-2 text-primary"></i>
                Today — {{ now()->format('D, d M Y') }}
            </h6>

            @if($todayRecord)
                <p class="mb-2">
                    Status:
                    <span class="badge badge-{{ $todayRecord['status'] ?? 'absent' }} ms-1">
                        {{ ucfirst(str_replace('_', ' ', $todayRecord['status'] ?? 'absent')) }}
                    </span>
                </p>
                @if($todayRecord['check_in'])
                    <p class="text-muted mb-2" style="font-size:0.85rem;">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Checked in: <strong>{{ $todayRecord['check_in'] }}</strong>
                    </p>
                @endif
                @if($todayRecord['check_out'])
                    <p class="text-muted mb-2" style="font-size:0.85rem;">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Checked out: <strong>{{ $todayRecord['check_out'] }}</strong>
                    </p>
                @endif
            @else
                <p class="text-muted mb-3" style="font-size:0.85rem;">You haven't checked in yet today.</p>
            @endif

            {{-- Action buttons --}}
            <div class="d-flex gap-2 mt-3">
                @if(!$checkedIn)
                    <form method="POST" action="{{ route('employee.checkin') }}" class="df-submit-form">
                        @csrf
                        <button type="submit" class="btn btn-success df-submit-btn">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Check In
                        </button>
                    </form>
                @elseif(!$checkedOut)
                    <form method="POST" action="{{ route('employee.checkout') }}" class="df-submit-form">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger df-submit-btn">
                            <i class="bi bi-box-arrow-right me-1"></i>Check Out
                        </button>
                    </form>
                @else
                    <button class="btn btn-secondary" disabled>
                        <i class="bi bi-check2-circle me-1"></i>Done for Today
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Attendance history table ─────────────────────────────────────────────── --}}
<div class="stat-card">
    <h6 class="fw-semibold mb-3">
        <i class="bi bi-table me-2"></i>Attendance History
    </h6>

    @if(empty($records))
        <p class="text-muted">No attendance records found.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        <tr>
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
