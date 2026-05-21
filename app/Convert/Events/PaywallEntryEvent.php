<?php

namespace App\Convert\Events;

class PaywallEntryEvent extends PaywallSessionEvent
{
    public function name(): string
    {
        return 'entry';
    }

    public function data(): ?array
    {
        return [];
    }
}
