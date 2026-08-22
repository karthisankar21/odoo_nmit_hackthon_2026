<?php
// =============================================================================
// app/Http/Controllers/Admin/AttendanceController.php
// -----------------------------------------------------------------------------
// Admin attendance: view all attendance records across all employees.
// =============================================================================

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    // =========================================================================
    // INDEX — GET /admin/attendance
    // =========================================================================

    /**
     * Display all attendance records for all employees.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('AdminAttendance: loading all attendance records');

        $res = ApiClient::get('/attendance');

        if (!$res['success']) {
            Log::warning('AdminAttendance: failed to load records', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        $records = $res['data']['attendance'] ?? [];

        return view('admin.attendance', compact('records'));
    }
}
