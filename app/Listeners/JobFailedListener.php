<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;

class JobFailedListener
{
    public function __invoke(JobFailed $failed)
    {
        logger()->info(logname(), [
            'job' => $failed->job->getName(),
            'exception' => $failed->exception?->getMessage(),
        ]);
    }
}
