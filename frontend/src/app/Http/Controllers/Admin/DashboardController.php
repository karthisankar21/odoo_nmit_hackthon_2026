<?php
// =============================================================================
// app/Http/Controllers/Admin/DashboardController.php
// -----------------------------------------------------------------------------
// Admin dashboard: renders analytics summary (headcount, attendance, leaves,
// leave types, department breakdown) from the Flask analytics API.
// =============================================================================

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // =========================================================================
    // INDEX — GET /admin/dashboard
    // =========================================================================

    /**
     * Display the admin analytics dashboard.
     *
     * Fetches /analytics/summary which returns:
     *   - total_employees
     *   - today_attendance  { present, absent, half_day, leave, total }
     *   - pending_leaves
     *   - leave_by_type     { paid, sick, unpaid, other }
     *   - dept_headcount    [ { department, count } ]
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('AdminDashboard: loading analytics summary');

        $res = ApiClient::get('/analytics/summary');

        $summary      = [];
        $deptData     = [];
        $leaveTypeData = [];

        if ($res['success']) {
            $raw = $res['data'];

            // ── Normalise flat API keys → nested structure the view expects ──────
            // Flask returns: present_today, absent_today, on_leave_today,
            //                half_day_today, pending_leave_requests,
            //                department_headcount (object), leave_by_type (object)
            // View expects:  today_attendance{present,absent,leave,half_day,total},
            //                pending_leaves, dept_headcount[{department,count}]

            $present  = $raw['present_today']      ?? 0;
            $absent   = $raw['absent_today']        ?? 0;
            $onLeave  = $raw['on_leave_today']      ?? 0;
            $halfDay  = $raw['half_day_today']      ?? 0;

            $summary = [
                'total_employees'  => $raw['total_employees'] ?? 0,
                'pending_leaves'   => $raw['pending_leave_requests'] ?? 0,
                'leave_by_type'    => $raw['leave_by_type'] ?? [],
                'today_attendance' => [
                    'present'  => $present,
                    'absent'   => $absent,
                    'leave'    => $onLeave,
                    'half_day' => $halfDay,
                    'total'    => max($present + $absent + $onLeave + $halfDay, 1),
                ],
                // dept_headcount comes as {"HR":1,"Tech":2} — convert to [{department,count}]
                'dept_headcount'   => collect($raw['department_headcount'] ?? [])
                    ->map(fn($count, $dept) => ['department' => $dept, 'count' => $count])
                    ->values()
                    ->all(),
            ];

            // Build chart-ready arrays for department headcount
            foreach ($summary['dept_headcount'] as $row) {
                $deptData[] = [
                    'label' => $row['department'] ?? 'Unknown',
                    'value' => $row['count'] ?? 0,
                ];
            }

            // Build leave-by-type summary
            $leaveTypeData = $summary['leave_by_type'] ?? [];
        } else {
            Log::warning('AdminDashboard: failed to load analytics', ['error' => $res['error']]);
        }

        return view('admin.dashboard', compact('summary', 'deptData', 'leaveTypeData'));
    }
}
