<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening headers. The Content-Security-Policy is
 * report-only for now: watch the browser console for violations on every
 * page, then switch to the enforcing `Content-Security-Policy` header.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy());

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Inline styles are allowed because React style props and Inertia's
     * page payload need them; the Vite dev server origins are added only
     * in local so hot reload keeps working.
     */
    private function contentSecurityPolicy(): string
    {
        $isLocal = app()->environment('local');
        $vite = $isLocal ? ' http://localhost:5173' : '';
        $viteSockets = $isLocal ? ' ws://localhost:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'{$vite}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$vite}",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob:",
            "connect-src 'self'{$vite}{$viteSockets}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
