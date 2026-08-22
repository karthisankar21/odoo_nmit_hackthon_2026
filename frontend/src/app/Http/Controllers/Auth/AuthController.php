<?php
// =============================================================================
// app/Http/Controllers/Auth/AuthController.php
// -----------------------------------------------------------------------------
// Handles login, registration and logout for the Dayflow HRMS frontend.
//
// Methods:
//   showLogin()    — renders the login form (GET /login)
//   login()        — submits credentials to backend, stores session (POST /login)
//   showRegister() — renders the registration form (GET /register)
//   register()     — submits new user to backend, stores session (POST /register)
//   logout()       — clears session, redirects to login (POST /logout)
// =============================================================================

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // =========================================================================
    // METHOD: showLogin
    // =========================================================================

    /**
     * Renders the login page.
     *
     * If the user is already logged in (token in session),
     * redirect them straight to their dashboard.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showLogin()
    {
        // Already logged in — redirect to appropriate dashboard
        if (session('token')) {
            Log::debug('AuthController@showLogin: already logged in, redirecting');
            return session('role') === 'admin'
                ? redirect()->route('admin.dashboard')
                : redirect()->route('employee.dashboard');
        }

        return view('auth.login');
    }

    // =========================================================================
    // METHOD: login
    // =========================================================================

    /**
     * Processes the login form submission.
     *
     * Steps:
     *   1. Validate email + password are present
     *   2. POST to /api/auth/login via ApiClient
     *   3. On success: store token, user_id, name, email, role in session
     *   4. Redirect to role-appropriate dashboard
     *   5. On failure: return to login page with inline error message
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        // ── Step 1: Validate form fields ──────────────────────────────────────
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:1',
        ]);

        $email    = $request->input('email');
        $password = $request->input('password');

        Log::debug('AuthController@login: attempt for email=' . $email);

        // ── Step 2: Call backend login API ────────────────────────────────────
        $response = ApiClient::post('/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        // ── Step 3: Handle failure ────────────────────────────────────────────
        if (!$response['success']) {
            Log::warning('AuthController@login: failed for email=' . $email . ' — ' . $response['error']);
            return back()->withInput(['email' => $email])
                         ->with('error', $response['error']);
        }

        // ── Step 4: Store session data ────────────────────────────────────────
        $data = $response['data'];

        session([
            'token'   => $data['token'],
            'user_id' => $data['user_id'],
            'name'    => $data['name'],
            'email'   => $data['email'],
            'role'    => $data['role'],
        ]);

        Log::debug('AuthController@login: success — user_id=' . $data['user_id'] . ' role=' . $data['role']);

        // ── Step 5: Redirect to role-appropriate dashboard ────────────────────
        return $data['role'] === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('employee.dashboard');
    }

    // =========================================================================
    // METHOD: showRegister
    // =========================================================================

    /**
     * Renders the registration page.
     *
     * If the user is already logged in redirect to their dashboard.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showRegister()
    {
        // Already logged in — redirect to appropriate dashboard
        if (session('token')) {
            Log::debug('AuthController@showRegister: already logged in, redirecting');
            return session('role') === 'admin'
                ? redirect()->route('admin.dashboard')
                : redirect()->route('employee.dashboard');
        }

        return view('auth.register');
    }

    // =========================================================================
    // METHOD: register
    // =========================================================================

    /**
     * Processes the registration form submission.
     *
     * Steps:
     *   1. Validate name, email, password, role
     *   2. POST to /api/auth/register via ApiClient
     *   3. On success: store token + session, redirect to dashboard
     *   4. On failure: return to register page with inline error
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function register(Request $request)
    {
        // ── Step 1: Validate form fields ──────────────────────────────────────
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:6',
            'role'     => 'required|in:employee,admin',
        ]);

        $name     = $request->input('name');
        $email    = $request->input('email');
        $password = $request->input('password');
        $role     = $request->input('role', 'employee');

        Log::debug('AuthController@register: attempt for email=' . $email . ' role=' . $role);

        // ── Step 2: Call backend register API ─────────────────────────────────
        $response = ApiClient::post('/auth/register', [
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'role'     => $role,
        ]);

        // ── Step 3: Handle failure ────────────────────────────────────────────
        if (!$response['success']) {
            Log::warning('AuthController@register: failed for email=' . $email . ' — ' . $response['error']);
            return back()->withInput(['name' => $name, 'email' => $email, 'role' => $role])
                         ->with('error', $response['error']);
        }

        // ── Step 4: Store session data (auto-login after registration) ────────
        $data = $response['data'];

        session([
            'token'   => $data['token'],
            'user_id' => $data['user_id'],
            'name'    => $data['name'],
            'email'   => $data['email'],
            'role'    => $data['role'],
        ]);

        Log::debug('AuthController@register: success — user_id=' . $data['user_id'] . ' role=' . $data['role']);

        // ── Step 5: Redirect to role-appropriate dashboard ────────────────────
        return $data['role'] === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('employee.dashboard');
    }

    // =========================================================================
    // METHOD: logout
    // =========================================================================

    /**
     * Logs the user out.
     *
     * Steps:
     *   1. Call backend /api/auth/logout (stateless — discards JWT server-side log)
     *   2. Flush the Laravel session
     *   3. Redirect to login page
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        $userId = session('user_id');
        Log::debug('AuthController@logout: user_id=' . $userId);

        // Notify backend (best-effort — non-fatal if it fails)
        ApiClient::post('/auth/logout');

        // Flush all session data
        session()->flush();

        Log::debug('AuthController@logout: session cleared for user_id=' . $userId);

        return redirect()->route('login');
    }
}
