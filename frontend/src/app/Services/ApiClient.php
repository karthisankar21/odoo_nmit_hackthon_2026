<?php
// =============================================================================
// app/Services/ApiClient.php
// -----------------------------------------------------------------------------
// Single HTTP client wrapper for all calls to the Flask backend API.
//
// Every controller uses this class instead of calling Http:: directly.
// This centralises:
//   - Base URL configuration (BACKEND_URL from .env)
//   - JWT token injection (Authorization: Bearer <token> from session)
//   - Error handling (network errors, non-2xx responses)
//   - Logging (debug on success, warning on non-2xx, error on exception)
//
// Usage in a controller:
//   $response = ApiClient::get('/employees/me');
//   if (!$response['success']) {
//       return back()->with('error', $response['error']);
//   }
//   $employee = $response['data'];
// =============================================================================

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ApiClient
{
    // =========================================================================
    // HELPER: baseUrl
    // =========================================================================

    /**
     * Returns the Flask backend base URL from config.
     * Set BACKEND_URL in .env → e.g. http://backend:5000
     *
     * @return string
     */
    private static function baseUrl(): string
    {
        return rtrim(config('app.backend_url', env('BACKEND_URL', 'http://backend:5000')), '/');
    }

    // =========================================================================
    // HELPER: headers
    // =========================================================================

    /**
     * Builds the HTTP headers for every request.
     * Injects the JWT token from the Laravel session if the user is logged in.
     *
     * @return array
     */
    private static function headers(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        // Attach JWT token if present in session (set on login)
        $token = Session::get('token');
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    // =========================================================================
    // HELPER: handleResponse
    // =========================================================================

    /**
     * Processes a Laravel HTTP response into a consistent array.
     *
     * Returns:
     *   ['success' => true,  'data' => [...]]       on 2xx
     *   ['success' => false, 'error' => 'message']  on non-2xx
     *
     * @param  \Illuminate\Http\Client\Response  $response
     * @param  string  $method   HTTP verb (for logging)
     * @param  string  $path     API path (for logging)
     * @return array
     */
    private static function handleResponse($response, string $method, string $path): array
    {
        $status = $response->status();

        if ($response->successful()) {
            // 2xx — success
            Log::debug("ApiClient {$method} {$path} → {$status}");
            return [
                'success' => true,
                'data'    => $response->json() ?? [],
                'status'  => $status,
            ];
        }

        // Non-2xx — extract error message from JSON body if available
        $body  = $response->json() ?? [];
        $error = $body['error'] ?? $body['message'] ?? "HTTP {$status} error";

        Log::warning("ApiClient {$method} {$path} → {$status}", [
            'error' => $error,
            'body'  => $body,
        ]);

        // 401 — JWT expired or invalid; clear session so middleware redirects to login
        if ($status === 401) {
            Log::warning("ApiClient: 401 on {$path} — clearing session and forcing re-login");
            session()->flush();
        }

        return [
            'success' => false,
            'error'   => $error,
            'status'  => $status,
        ];
    }

    // =========================================================================
    // PUBLIC: get
    // =========================================================================

    /**
     * Performs a GET request to the Flask API.
     *
     * @param  string  $path    API path e.g. '/employees/me'
     * @param  array   $query   Optional query string params
     * @return array            ['success' => bool, 'data'|'error' => ...]
     */
    public static function get(string $path, array $query = []): array
    {
        $url = self::baseUrl() . '/api' . $path;
        Log::debug("ApiClient GET {$path}");

        try {
            $request = Http::withHeaders(self::headers())->timeout(10);
            $response = empty($query)
                ? $request->get($url)
                : $request->get($url, $query);

            return self::handleResponse($response, 'GET', $path);

        } catch (\Exception $e) {
            Log::error("ApiClient GET {$path} exception: " . $e->getMessage(), [
                'url' => $url,
            ]);
            return ['success' => false, 'error' => 'Service unavailable — could not reach backend'];
        }
    }

    // =========================================================================
    // PUBLIC: post
    // =========================================================================

    /**
     * Performs a POST request to the Flask API.
     *
     * @param  string  $path   API path e.g. '/auth/login'
     * @param  array   $body   JSON request body
     * @return array
     */
    public static function post(string $path, array $body = []): array
    {
        $url = self::baseUrl() . '/api' . $path;
        Log::debug("ApiClient POST {$path}");

        try {
            $response = Http::withHeaders(self::headers())
                ->timeout(10)
                ->post($url, $body);

            return self::handleResponse($response, 'POST', $path);

        } catch (\Exception $e) {
            Log::error("ApiClient POST {$path} exception: " . $e->getMessage(), [
                'url' => $url,
            ]);
            return ['success' => false, 'error' => 'Service unavailable — could not reach backend'];
        }
    }

    // =========================================================================
    // PUBLIC: put
    // =========================================================================

    /**
     * Performs a PUT request to the Flask API.
     *
     * @param  string  $path   API path e.g. '/leave/3/approve'
     * @param  array   $body   JSON request body
     * @return array
     */
    public static function put(string $path, array $body = []): array
    {
        $url = self::baseUrl() . '/api' . $path;
        Log::debug("ApiClient PUT {$path}");

        try {
            $response = Http::withHeaders(self::headers())
                ->timeout(10)
                ->put($url, $body);

            return self::handleResponse($response, 'PUT', $path);

        } catch (\Exception $e) {
            Log::error("ApiClient PUT {$path} exception: " . $e->getMessage(), [
                'url' => $url,
            ]);
            return ['success' => false, 'error' => 'Service unavailable — could not reach backend'];
        }
    }
}
