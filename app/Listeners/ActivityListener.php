<?php

namespace App\Listeners;

use App\Convert\Events\Activity\ActivityStartedEvent;

class ActivityListener
{
    public function started(ActivityStartedEvent $event)
    {
         // get activity (event->state)
    }
}
