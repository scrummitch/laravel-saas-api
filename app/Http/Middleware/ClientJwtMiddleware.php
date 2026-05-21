<?php

namespace App\Http\Middleware;

use App\Client\ClientAuthorization;
use Exception;
use Illuminate\Http\Request;

class ClientJwtMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        try {
            $jwt = new ClientAuthorization($request->bearerToken() ?? $request->json('auth'));

            app()->instance(ClientAuthorization::class, $jwt);

            return $next($request);
        } catch (Exception $e) {
            if (app()->runningUnitTests()) {
                dd($e);
            }

            logger()->error(logname(), [
                'msg' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 403);
        }
    }
}
