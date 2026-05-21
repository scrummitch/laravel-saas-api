<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HealthCheckController extends Controller
{
    public function __invoke()
    {
        $db = null;
        $status = 200;

        try {
            $db = DB::connection()->getPdo();
        } catch (\Throwable $e) {
            report($e);
            $status = 500;
        }

        return response([
            'app' => config('app.url'),
            'status' => 'ok',
            'version' => config('sentry.release'),
            'db' => $db ? 'ok' : 'fail',
            'environment' => config('app.env'),
        ], $status);
    }
}
