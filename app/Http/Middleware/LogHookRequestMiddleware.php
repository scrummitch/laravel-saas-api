<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogHookRequestMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        /* @var Response $response */
        $response = $next($request);

        logger()->debug(logname(), [
            'path' => $request->path(),
            'method' => $request->method(),
            'response_status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
