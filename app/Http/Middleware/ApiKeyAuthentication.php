<?php

namespace App\Http\Middleware;

use App\Models\EmailDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required. Please provide X-API-Key header.',
            ], 401);
        }

        // Find domain by API key
        $domain = EmailDomain::byApiKey($apiKey)->active()->first();

        if (!$domain) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive API key.',
            ], 401);
        }

        // Attach domain to request for later use
        $request->merge(['authenticated_domain' => $domain]);

        return $next($request);
    }
}
