<?php
// =============================================================================
// app/Http/Controllers/Admin/EmployeeController.php
// -----------------------------------------------------------------------------
// Admin employee management: list all employees, view/edit individual profiles.
// =============================================================================

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    // =========================================================================
    // INDEX — GET /admin/employees
    // =========================================================================

    /**
     * List all employees.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('AdminEmployee: listing all employees');

        $res = ApiClient::get('/employees');

        if (!$res['success']) {
            Log::warning('AdminEmployee: failed to list employees', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        $employees = $res['data']['employees'] ?? [];

        return view('admin.employees.index', compact('employees'));
    }

    // =========================================================================
    // SHOW — GET /admin/employees/{id}
    // =========================================================================

    /**
     * Show a single employee's profile.
     *
     * @param  int  $id  Employee user ID
     * @return \Illuminate\View\View
     */
    public function show(int $id)
    {
        Log::debug("AdminEmployee: showing employee id={$id}");

        $res = ApiClient::get("/employees/{$id}");

        if (!$res['success']) {
            Log::warning("AdminEmployee: employee {$id} not found", ['error' => $res['error']]);
            return redirect()->route('admin.employees')->with('error', $res['error']);
        }

        $employee = $res['data'] ?? [];

        return view('admin.employees.show', compact('employee', 'id'));
    }

    // =========================================================================
    // UPDATE — POST /admin/employees/{id}
    // =========================================================================

    /**
     * Update an employee's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Employee user ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id)
    {
        Log::debug("AdminEmployee: updating employee id={$id}");

        $validated = $request->validate([
            'phone'      => 'nullable|string|max:20',
            'address'    => 'nullable|string|max:255',
            'job_title'  => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        $res = ApiClient::put("/employees/{$id}", $validated);

        if (!$res['success']) {
            Log::warning("AdminEmployee: update failed for id={$id}", ['error' => $res['error']]);
            return back()->with('error', $res['error'])->withInput();
        }

        Log::debug("AdminEmployee: employee {$id} updated successfully");

        return redirect()->route('admin.employees.show', $id)
            ->with('success', 'Employee profile updated.');
    }
}
