<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $sessionToken = $request->session()->token();
        
        // Try all possible locations for the request token
        $requestToken = $this->getTokenFromRequest($request);

        if (! $sessionToken || ! $requestToken) {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function getTokenFromRequest($request)
    {
        // Try _token input first
        $token = $request->input('_token');
        
        // Try X-CSRF-TOKEN header
        if (! $token) {
            $token = $request->header('X-CSRF-TOKEN');
        }
        
        // Try X-XSRF-TOKEN header (encrypted)
        if (! $token && $header = $request->header('X-XSRF-TOKEN')) {
            try {
                $token = $this->encrypter->decrypt($header);
            } catch (\Exception $e) {
                // Token decryption failed, try next
            }
        }
        
        // For Livewire/Filament AJAX requests
        if (! $token) {
            $token = $request->header('X-Livewire');
        }

        return $token;
    }
}
