<?php

namespace App\Console;

use App\Jobs\ResetDemoAccountJob;
use App\Jobs\Schedule\SubscriptionsBillerJob;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if ($heartbeatUrl = config('app.heartbeat_url')) {
            $schedule->call(function () use ($heartbeatUrl) {
                try {
                    Http::timeout(10)
                    ->retry(3, 1000, function ($exception) {
                        return $exception instanceof ConnectionException ||
                            $exception instanceof RequestException;
                    }, false)
                    ->get($heartbeatUrl);
                } catch (Exception $e) {
                    logger()->error('Heartbeat ping failed: ' . $e->getMessage());
                }
            })->everyFiveMinutes();
        }

        $schedule->job(ResetDemoAccountJob::class)
            ->dailyAt('14:00');

        $schedule->job(new SubscriptionsBillerJob)
            ->hourlyAt(10)
            ->name('schedule:bill_customers')
            ->onOneServer()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
