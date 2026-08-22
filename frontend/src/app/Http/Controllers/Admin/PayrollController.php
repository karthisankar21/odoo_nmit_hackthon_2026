<?php
// =============================================================================
// app/Http/Controllers/Admin/PayrollController.php
// -----------------------------------------------------------------------------
// Admin payroll management: view all payroll records, update (upsert) a record.
// =============================================================================

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayrollController extends Controller
{
    // =========================================================================
    // INDEX — GET /admin/payroll
    // =========================================================================

    /**
     * Display all employee payroll records.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('AdminPayroll: loading all payroll records');

        $res = ApiClient::get('/payroll');

        if (!$res['success']) {
            Log::warning('AdminPayroll: failed to load payroll', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        $payrolls = $res['data']['payrolls'] ?? [];

        return view('admin.payroll', compact('payrolls'));
    }

    // =========================================================================
    // UPDATE — POST /admin/payroll/{id}
    // =========================================================================

    /**
     * Create or update (upsert) a payroll record for an employee.
     *
     * Accepted fields: basic_salary, allowances, deductions, month, year
     * net_salary is always computed server-side by Flask.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Employee user ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id)
    {
        Log::debug("AdminPayroll: updating payroll for employee id={$id}");

        $validated = $request->validate([
            'basic_salary' => 'required|numeric|min:0',
            'allowances'   => 'required|numeric|min:0',
            'deductions'   => 'required|numeric|min:0',
            'month'        => 'required|integer|min:1|max:12',
            'year'         => 'required|integer|min:2000|max:2100',
        ]);

        $res = ApiClient::put("/payroll/{$id}", $validated);

        if (!$res['success']) {
            Log::warning("AdminPayroll: update failed for id={$id}", ['error' => $res['error']]);
            return back()->with('error', $res['error'])->withInput();
        }

        Log::debug("AdminPayroll: payroll updated for employee {$id}");

        return redirect()->route('admin.payroll')
            ->with('success', "Payroll for employee #{$id} saved successfully.");
    }
}
