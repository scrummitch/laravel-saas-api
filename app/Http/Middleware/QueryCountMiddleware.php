<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QueryCountMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            DB::enableQueryLog();
        }

        $response = $next($request);

        if (app()->environment('local')) {
            $queries = DB::getQueryLog();
            $totalTime = collect($queries)->sum('time');
            $queryCount = count($queries);
            $response->headers->add([
                'X-Query-Count' => $queryCount,
                'X-Query-Time' => $totalTime,
            ]);

            // list all queries and write to a file with their times
            $log = '';
            foreach ($queries as $query) {
                $log .= $query['time'].'ms '.$query['query'].PHP_EOL;
            }
            file_put_contents(storage_path('logs/query.log'), $log);
        }

        return $response;
    }
}
