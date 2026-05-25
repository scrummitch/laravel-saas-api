<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\Intelligence\Scenario;
use Closure;
use Fruitcake\Cors\CorsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientApiCorsMiddleware
{
    protected $cors;

    public function __construct(CorsService $cors)
    {
        $this->cors = $cors;
    }

    protected function originIsNotUsuallyAllowed(Request $request): bool
    {
        $origin = (string) $request->headers->get('Origin');

        return !in_array($origin, config('cors.allowed_origins') ?? []);
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->is('v1/convert/paywalls/*')
            && $this->originIsNotUsuallyAllowed($request)
        ) {
            $id = Str::after($request->path(), 'v1/convert/paywalls/');

            $scenario = Scenario::retrieve($id);
            $scenario?->loadMissing(['flow', 'flow.clients']);

            $origins = $scenario?->flow->clients->pluck('allowed_origins')
                ->flatten()
                ->filter()
                ->toArray() ?? [];

            $this->cors->setOptions([
                'paths' => ['*'],
                'allowed_methods' => ['*'],
                'allowed_origins' => $origins,
                'allowed_origins_patterns' => [],
                'allowed_headers' => [
                    'X-Requested-With',
                    'Authorization',
                    'User-Agent',
                    'X-Xsrf-Token',
                    'Accept',
                    'Accept-*',
                    'Sec-*',
                    'X-Version',
                    'Access-Control-Request-Headers',
                    'Access-Control-Request-Method',
                    'Host',
                    'Content-Type',
                    'Referer',
                    'Referrer-*',
                    'User-Agent',
                ],
                'exposed_headers' => [],
                'max_age' => 0,
                'supports_credentials' => false,
            ]);

            if ($this->cors->isPreflightRequest($request)) {
                $response = $this->cors->handlePreflightRequest($request);

                $this->cors->varyHeader($response, 'Access-Control-Request-Method');

                return $response;
            }

            $response = $next($request);

            return $this->cors->addActualRequestHeaders($response, $request);
        }

        if (! $this->hasMatchingPath($request)) {
            return $next($request);
        }

        $origin = (string) $request->headers->get('Origin');
        $allowedOrigins = $this->resolveAllowedOriginsForClientApi($request, $origin);

        $this->cors->setOptions([
            'paths' => ['*'],
            'allowed_methods' => ['*'],
            'allowed_origins' => $allowedOrigins,
            'allowed_origins_patterns' => [],
            'allowed_headers' => [
                'X-Requested-With',
                'Authorization',
                'User-Agent',
                'X-Xsrf-Token',
                'Accept',
                'Accept-*',
                'Sec-*',
                'X-Version',
                'Access-Control-Request-Headers',
                'Access-Control-Request-Method',
                'Plandalf-Cart',
                'Host',
                'Content-Type',
                'Referer',
                'Referrer-*',
                'User-Agent',
            ],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => false,
        ]);

        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);

            $this->cors->varyHeader($response, 'Access-Control-Request-Method');

            return $response;
        }

        $response = $next($request);

        if ($request->getMethod() === 'OPTIONS') {
            $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        return $this->cors->addActualRequestHeaders($response, $request);
    }

    /**
     * Resolve the allowed origins for a request hitting the generic /client/* API.
     *
     * Returns an allow-list — never `*`. The request origin must match either:
     *   - the global trusted-origin list in `config/cors.php` (operator-managed), or
     *   - the `allowed_origins` of the specific client identified by `?client=<ulid>`.
     *
     * The SDK always passes `?client=<ulid>` on every request (including the
     * CORS preflight OPTIONS, since the param lives in the URL not a header),
     * so we can scope the trust check to exactly one client — no bulk load.
     *
     * If the origin matches we echo it back as the sole allowed origin.
     * Otherwise we return an empty list and the browser refuses the response.
     */
    protected function resolveAllowedOriginsForClientApi(Request $request, string $origin): array
    {
        if ($origin === '') {
            return [];
        }

        if (in_array($origin, array_filter(config('cors.allowed_origins') ?? []), true)) {
            return [$origin];
        }

        $kid = $request->query('client');

        if (! is_string($kid) || $kid === '') {
            return [];
        }

        if (in_array($origin, Client::allowedOriginsFor($kid), true)) {
            return [$origin];
        }

        return [];
    }

    /**
     * Get the path from the configuration to determine if the CORS service should run.
     */
    protected function hasMatchingPath(Request $request): bool
    {
        $paths = $this->getPathsByHost($request->getHost());

        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($request->fullUrlIs($path) || $request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the CORS paths for the given host.
     *
     * @return array
     */
    protected function getPathsByHost(string $host)
    {
        $paths = [
            'client/*',
        ];

        if (isset($paths[$host])) {
            return $paths[$host];
        }

        return array_filter($paths, function ($path) {
            return is_string($path);
        });
    }
}
