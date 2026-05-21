<?php

namespace App\Convert\Events;

class PaywallStartEvent extends PaywallSessionEvent
{
    public function name(): string
    {
        return 'start';
    }

    public function data(): ?array
    {
        return null;
    }
}
