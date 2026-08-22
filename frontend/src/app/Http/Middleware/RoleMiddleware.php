<?php
// =============================================================================
// app/Http/Middleware/RoleMiddleware.php
// -----------------------------------------------------------------------------
// Guards routes by user role.
//
// After AuthMiddleware confirms a token exists, RoleMiddleware checks that
// the session role matches what the route requires.
//
// Usage in routes/web.php:
//   Route::middleware(['auth.custom', 'role:admin'])->group(...)
//   Route::middleware(['auth.custom', 'role:employee'])->group(...)
// =============================================================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Reads the required role from the middleware parameter ($role),
     * compares it against session('role') set at login.
     *
     * Mismatch → log warning and redirect to the user's own dashboard.
     * Match    → allow request through.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string   $role   Required role: 'employee' or 'admin'
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $actualRole = session('role', '');

        if ($actualRole !== $role) {
            Log::warning('RoleMiddleware: role mismatch', [
                'required' => $role,
                'actual'   => $actualRole,
                'user_id'  => session('user_id'),
                'path'     => $request->path(),
            ]);

            // Redirect to the user's correct dashboard instead of showing 403
            if ($actualRole === 'admin') {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('employee.dashboard');
        }

        Log::debug("RoleMiddleware: role={$actualRole} OK for " . $request->path());

        return $next($request);
    }
}
