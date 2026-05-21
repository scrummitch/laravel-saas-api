<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogRequestMiddleware
{
    protected $slowQueries = [];

    public function handle(Request $request, Closure $next)
    {
        $shouldLog = (!app()->runningUnitTests())
            && 0
            && $request->header('User-Agent') !== 'ELB-HealthChecker/2.0'
            && ! Str::startsWith($request->path(), 'livewire/')
            && ! Str::startsWith($request->userAgent(), 'Better Uptime Bot')
            && ! in_array($request->method(), ['OPTIONS', 'HEAD']);

        $startMemory = memory_get_usage();

        $this->setupPerformanceMonitoring();

        $response = $next($request);

        $endMemory = memory_get_usage();

        $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024; // in MB

        $reqTime = number_format((microtime(true) - $request->server->get('REQUEST_TIME_FLOAT')) * 1000, 2);

        if ($shouldLog) {
            $logData = array_filter([
                'request_id' => $request->header('X-Request-ID'),
                'path' => $request->path(),
                'method' => $request->method(),
                'response_status' => $response->getStatusCode(),
                'user_id' => optional($request->user())->id,
                'ip' => $request->ip(),
                'request_time' => $reqTime.'ms',
                'memory_usage' => number_format($memoryUsage, 2).'MB',
                'query_count' => count($this->slowQueries),
                'route' => $request->route() ? $request->route()->getName() : 'N/A',
                'controller_action' => $request->route() ? $request->route()->getActionName() : 'N/A',
                'slow_queries' => empty($this->slowQueries) ? null : $this->slowQueries,
            ]);

            Log::info(logname(), $logData);
        }

        return $response;
    }

    private function setupPerformanceMonitoring()
    {
        DB::listen(function ($query) {
            $time = $query->time;
            if ($time > 100) { // Log queries taking more than 100ms
                $this->slowQueries[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $time,
                ];
            }
        });
    }
}
