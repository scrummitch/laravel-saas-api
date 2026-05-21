<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class RespondWithJsonMiddleware
{
    public function handle(Request $request, $next)
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        return $response;
    }
}
