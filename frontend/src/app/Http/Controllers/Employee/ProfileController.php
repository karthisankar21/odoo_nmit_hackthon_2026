<?php
// =============================================================================
// app/Http/Controllers/Employee/ProfileController.php
// -----------------------------------------------------------------------------
// Handles employee's own profile: view and update.
// =============================================================================

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    // =========================================================================
    // INDEX — GET /employee/profile
    // =========================================================================

    /**
     * Display the authenticated employee's profile.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        Log::debug('EmpProfile: fetching profile for user ' . session('user_id'));

        // Fetch current employee profile from Flask API
        $res = ApiClient::get('/employees/me');

        if (!$res['success']) {
            Log::warning('EmpProfile: failed to load profile', ['error' => $res['error']]);
            return back()->with('error', $res['error']);
        }

        $employee = $res['data']['employee'] ?? [];

        return view('employee.profile', compact('employee'));
    }

    // =========================================================================
    // UPDATE — POST /employee/profile
    // =========================================================================

    /**
     * Update the authenticated employee's profile.
     *
     * Accepts: phone, address, job_title, department
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        Log::debug('EmpProfile: update request from user ' . session('user_id'));

        // Validate form fields
        $validated = $request->validate([
            'phone'      => 'nullable|string|max:20',
            'address'    => 'nullable|string|max:255',
            'job_title'  => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        // Send PUT request to Flask API
        $res = ApiClient::put('/employees/me', $validated);

        if (!$res['success']) {
            Log::warning('EmpProfile: update failed', ['error' => $res['error']]);
            return back()->with('error', $res['error'])->withInput();
        }

        Log::debug('EmpProfile: profile updated successfully');

        return redirect()->route('employee.profile')
            ->with('success', 'Profile updated successfully.');
    }
}
