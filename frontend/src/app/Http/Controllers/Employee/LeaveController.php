<?php
// =============================================================================
// app/Http/Controllers/Employee/LeaveController.php
// -----------------------------------------------------------------------------
// Handles employee leave: view history and apply for leave.
// =============================================================================

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeaveController extends Controller
{
    // =========================================================================
    // INDEX — GET /employee/leave
    // =========================================================================

    /**
     * Show the employee's leave history and the apply-leave form.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('EmpLeave: loading leave records for user ' . session('user_id'));

        $res = ApiClient::get('/leave/me');

        if (!$res['success']) {
            Log::warning('EmpLeave: failed to load leaves', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        $leaves = $res['data']['leaves'] ?? [];

        return view('employee.leave', compact('leaves'));
    }

    // =========================================================================
    // APPLY — POST /employee/leave
    // =========================================================================

    /**
     * Submit a new leave application.
     *
     * Accepted fields: leave_type, start_date, end_date, reason
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function apply(Request $request)
    {
        Log::debug('EmpLeave: applying for leave, user ' . session('user_id'));

        // Validate form input
        $validated = $request->validate([
            'leave_type' => 'required|in:annual,sick,unpaid,other',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|string|max:500',
        ]);

        // POST to Flask API
        $res = ApiClient::post('/leave', $validated);

        if (!$res['success']) {
            Log::warning('EmpLeave: application failed', ['error' => $res['error']]);
            return back()->with('error', $res['error'])->withInput();
        }

        Log::debug('EmpLeave: leave application submitted successfully');

        return redirect()->route('employee.leave')
            ->with('success', 'Leave application submitted and is pending approval.');
    }
}
