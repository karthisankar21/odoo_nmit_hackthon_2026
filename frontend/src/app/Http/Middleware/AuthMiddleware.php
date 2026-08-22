<?php
// =============================================================================
// app/Http/Middleware/AuthMiddleware.php
// -----------------------------------------------------------------------------
// Guards every protected route.
//
// Checks that the Laravel session contains a valid JWT token (set on login).
// If no token is found → redirects to /login.
//
// This middleware is applied to all routes EXCEPT /login and /logout.
// =============================================================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Checks session('token') — the JWT stored at login.
     * If missing → log a warning and redirect to login page.
     * If present → allow the request through.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for JWT token in session
        if (!session('token')) {
            Log::warning('AuthMiddleware: unauthenticated access to ' . $request->path() . ' — redirecting to login');
            return redirect()->route('login');
        }

        Log::debug('AuthMiddleware: authenticated user_id=' . session('user_id') . ' accessing ' . $request->path());

        return $next($request);
    }
}
