<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $cspNonce = base64_encode(random_bytes(16));
        view()->share('cspNonce', $cspNonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Best-effort: may be added by PHP/web server.
        $response->headers->remove('X-Powered-By');

        // Redirect responses don't need a body; stripping it reduces scanner noise.
        if (
            $response->isRedirection()
            && $response->headers->has('Location')
            && in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)
        ) {
            $response->setContent('');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $isLocal = app()->environment('local');
            $viteHotEnabled = $isLocal && is_file(public_path('hot'));

            // Only advertise the Vite dev server in CSP when it is actually in use.
            // Also avoid IPv6 bracket syntax ([::1]) which ZAP reports as
            // "Unrecognized source-expression".
            $viteHosts = $viteHotEnabled ? [
                'http://127.0.0.1:5173',
            ] : [];

            $viteWsHosts = $viteHotEnabled ? [
                'ws://127.0.0.1:5173',
            ] : [];

            $viteHttpHosts = $viteHotEnabled ? [
                'http://127.0.0.1:5173',
            ] : [];

            $scriptSrc = array_merge([
                "'self'",
                "'nonce-{$cspNonce}'",
                'https://cdn.jsdelivr.net',
            ], $viteHosts);

            $styleSrc = array_merge([
                "'self'",
                "'nonce-{$cspNonce}'",
                'https://cdn.jsdelivr.net',
            ], $viteHosts);

            $connectSrc = array_merge([
                "'self'",
            ], array_merge($viteHosts, $viteWsHosts));

            $csp = [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "object-src 'none'",
                'img-src '.implode(' ', array_unique(array_merge([
                    "'self'",
                    'data:',
                ], $viteHttpHosts))),
                "font-src 'self' data:",
                "script-src-attr 'unsafe-inline'",
                'script-src '.implode(' ', array_unique($scriptSrc)),
                "style-src-attr 'unsafe-inline'",
                'style-src '.implode(' ', array_unique($styleSrc)),
                'connect-src '.implode(' ', array_unique($connectSrc)),
            ];

            $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        return $response;
    }
}
