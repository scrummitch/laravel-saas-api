<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security-response headers. This is an API — no UI rendering — so:
 *
 *   - `X-Frame-Options: DENY` and `Content-Security-Policy: frame-ancestors 'none'`
 *     refuse framing entirely (clickjacking protection covering both old and new browsers).
 *   - `X-Content-Type-Options: nosniff` blocks MIME confusion attacks against any
 *     consumer that treats responses as web content.
 *   - `Referrer-Policy: no-referrer` keeps URL paths out of `Referer` headers when
 *     responses ever get loaded from a browser context (e.g. paywall redirects).
 *   - `Strict-Transport-Security` only outside of local/test so dev over plain http still works.
 *   - `Permissions-Policy` disables every browser feature — nothing API-served needs them.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        $response->headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

        if (! app()->environment(['local', 'testing'])) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
