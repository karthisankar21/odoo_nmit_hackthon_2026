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
     *   - leave_by_type     { annual, sick, unpaid, other }
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
            $summary = $res['data'];

            // Build chart-ready arrays for department headcount
            foreach ($summary['dept_headcount'] ?? [] as $row) {
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
