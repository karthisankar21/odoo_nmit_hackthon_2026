<?php
// =============================================================================
// app/Http/Controllers/Employee/DashboardController.php
// -----------------------------------------------------------------------------
// Employee dashboard: shows today's attendance status, pending leaves, and a
// quick summary of the current payroll record.
// =============================================================================

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // =========================================================================
    // INDEX — GET /employee/dashboard
    // =========================================================================

    /**
     * Display the employee dashboard.
     *
     * Fetches:
     *   - /attendance/me  → today's record (check-in status)
     *   - /leave/me       → pending leave requests count
     *   - /payroll/me     → latest payroll (net salary)
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('EmpDashboard: loading dashboard for user ' . session('user_id'));

        // ── Attendance: today's record ─────────────────────────────────────
        $attendanceRes = ApiClient::get('/attendance/me');
        $todayRecord   = null;
        $checkedIn     = false;

        if ($attendanceRes['success']) {
            $records = $attendanceRes['data']['attendance'] ?? [];
            // Find today's record
            $today = now()->toDateString();
            foreach ($records as $record) {
                if (($record['date'] ?? '') === $today) {
                    $todayRecord = $record;
                    $checkedIn   = !empty($record['check_in']);
                    break;
                }
            }
        }

        // ── Leave: count pending ───────────────────────────────────────────
        $leaveRes     = ApiClient::get('/leave/me');
        $pendingLeaves = 0;
        $recentLeaves  = [];

        if ($leaveRes['success']) {
            $allLeaves = $leaveRes['data']['leaves'] ?? [];
            foreach ($allLeaves as $l) {
                if (($l['status'] ?? '') === 'pending') {
                    $pendingLeaves++;
                }
            }
            // Take the 3 most recent leaves for display
            $recentLeaves = array_slice($allLeaves, 0, 3);
        }

        // ── Payroll: latest record ─────────────────────────────────────────
        $payrollRes = ApiClient::get('/payroll/me');
        $payroll    = null;
        if ($payrollRes['success']) {
            $payrolls = $payrollRes['data']['payrolls'] ?? [];
            $payroll  = !empty($payrolls) ? $payrolls[0] : null;
        }

        return view('employee.dashboard', compact(
            'todayRecord', 'checkedIn', 'pendingLeaves', 'recentLeaves', 'payroll'
        ));
    }
}
