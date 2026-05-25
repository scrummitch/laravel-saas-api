<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Laravel resolves `'*'` to "trust only the directly-connected upstream IP"
     * (i.e. `REMOTE_ADDR`) — not "trust every hop in X-Forwarded-For". With AWS
     * ELB in front, REMOTE_ADDR is always the ELB; Symfony's chain filter then
     * walks X-Forwarded-For right-to-left, drops trusted proxies, and returns
     * the right-most untrusted entry as `$request->ip()` — which is the real
     * client IP because ELB *appends* it to whatever the client sent.
     *
     * If we ever stop fronting the app with an L4/L7 proxy that appends
     * to X-Forwarded-For (e.g. switch to NLB in pass-through, or expose direct),
     * change `$proxies` to the proxy's CIDR or drop the header set entirely.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * Bundled flag covering `X-Forwarded-For`, `X-Forwarded-Port`, and
     * `X-Forwarded-Proto`. Deliberately excludes `X-Forwarded-Host` and the
     * RFC 7239 `Forwarded` header so a client can't spoof the request host
     * (which signed-URL generation and email links rely on). `TrustHosts`
     * is a second lock on the same door.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_AWS_ELB;
}
