<?php
// =============================================================================
// app/Http/Controllers/Employee/PayrollController.php
// -----------------------------------------------------------------------------
// Shows the employee's payroll records (read-only).
// =============================================================================

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Log;

class PayrollController extends Controller
{
    // =========================================================================
    // INDEX — GET /employee/payroll
    // =========================================================================

    /**
     * Display the employee's payroll history.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('EmpPayroll: loading payroll for user ' . session('user_id'));

        $res = ApiClient::get('/payroll/me');

        if (!$res['success']) {
            // Non-fatal: show empty state with inline warning rather than redirecting
            Log::warning('EmpPayroll: failed to load payroll — ' . ($res['error'] ?? ''));
            return view('employee.payroll', ['payrolls' => [], 'apiError' => $res['error']]);
        }

        $payrolls = $res['data']['payrolls'] ?? [];

        return view('employee.payroll', compact('payrolls'));
    }
}
