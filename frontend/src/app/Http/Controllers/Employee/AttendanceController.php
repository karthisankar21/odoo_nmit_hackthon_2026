<?php
// =============================================================================
// app/Http/Controllers/Employee/AttendanceController.php
// -----------------------------------------------------------------------------
// Handles employee attendance: view history, check in, check out.
// =============================================================================

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    // =========================================================================
    // INDEX — GET /employee/attendance
    // =========================================================================

    /**
     * Show the employee's attendance history and today's status.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('EmpAttendance: loading records for user ' . session('user_id'));

        $res = ApiClient::get('/attendance/me');

        if (!$res['success']) {
            Log::warning('EmpAttendance: failed to load records', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        $records = $res['data']['attendance'] ?? [];

        // Determine today's status for the check-in / check-out buttons
        $today       = now()->toDateString();
        $todayRecord = null;
        foreach ($records as $r) {
            if (($r['date'] ?? '') === $today) {
                $todayRecord = $r;
                break;
            }
        }

        $checkedIn  = !empty($todayRecord['check_in']);
        $checkedOut = !empty($todayRecord['check_out']);

        return view('employee.attendance', compact('records', 'todayRecord', 'checkedIn', 'checkedOut'));
    }

    // =========================================================================
    // CHECK IN — POST /employee/attendance/checkin
    // =========================================================================

    /**
     * Record check-in for today.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function checkIn(Request $request)
    {
        Log::debug('EmpAttendance: check-in for user ' . session('user_id'));

        $res = ApiClient::post('/attendance/check-in');

        if (!$res['success']) {
            Log::warning('EmpAttendance: check-in failed', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        return redirect()->route('employee.attendance')
            ->with('success', 'Checked in at ' . now()->format('H:i') . '.');
    }

    // =========================================================================
    // CHECK OUT — POST /employee/attendance/checkout
    // =========================================================================

    /**
     * Record check-out for today.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function checkOut(Request $request)
    {
        Log::debug('EmpAttendance: check-out for user ' . session('user_id'));

        $res = ApiClient::post('/attendance/check-out');

        if (!$res['success']) {
            Log::warning('EmpAttendance: check-out failed', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        return redirect()->route('employee.attendance')
            ->with('success', 'Checked out at ' . now()->format('H:i') . '.');
    }
}
