<?php
// =============================================================================
// app/Http/Controllers/Admin/LeaveController.php
// -----------------------------------------------------------------------------
// Admin leave management: view all leave requests, approve or reject them.
// =============================================================================

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeaveController extends Controller
{
    // =========================================================================
    // INDEX — GET /admin/leave
    // =========================================================================

    /**
     * Display all leave requests across all employees.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('AdminLeave: loading all leave requests');

        $res = ApiClient::get('/leave');

        if (!$res['success']) {
            Log::warning('AdminLeave: failed to load leaves', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        // Flask returns key "leave_requests" (not "leaves")
        $leaves = $res['data']['leave_requests'] ?? [];

        return view('admin.leave', compact('leaves'));
    }

    // =========================================================================
    // APPROVE — POST /admin/leave/{id}/approve
    // =========================================================================

    /**
     * Approve a leave request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  LeaveRequest ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Request $request, int $id)
    {
        Log::debug("AdminLeave: approving leave id={$id}");

        $res = ApiClient::put("/leave/{$id}/approve");

        if (!$res['success']) {
            Log::warning("AdminLeave: approve failed for id={$id}", ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        Log::debug("AdminLeave: leave {$id} approved");

        return redirect()->route('admin.leave')
            ->with('success', "Leave #$id approved successfully.");
    }

    // =========================================================================
    // REJECT — POST /admin/leave/{id}/reject
    // =========================================================================

    /**
     * Reject a leave request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  LeaveRequest ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, int $id)
    {
        Log::debug("AdminLeave: rejecting leave id={$id}");

        $res = ApiClient::put("/leave/{$id}/reject");

        if (!$res['success']) {
            Log::warning("AdminLeave: reject failed for id={$id}", ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        Log::debug("AdminLeave: leave {$id} rejected");

        return redirect()->route('admin.leave')
            ->with('success', "Leave #$id rejected.");
    }
}
