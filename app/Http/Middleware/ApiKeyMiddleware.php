<?php

namespace App\Http\Middleware;

use Closure;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ApiKeyMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $encryptedApiKey = $request->header('x-api-key');

        try {
            $apiKey = decrypt($encryptedApiKey);
        } catch (\Exception $e) {
            return $this->responseUnAuthenticated('Invalid API Key','Unauthorized');
        }

        // Get all valid API keys from config
        $validApiKeys = config('app.api_keys', []);

        // Check if the provided key exists in the array of valid keys
        if (!in_array($apiKey, $validApiKeys)) {
            return $this->responseUnAuthenticated('Invalid API Key','Unauthorized');
        }

        return $next($request);
    }

}



