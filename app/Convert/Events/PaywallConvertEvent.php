<?php

namespace App\Convert\Events;

class PaywallConvertEvent extends PaywallSessionEvent
{
    public function name(): string
    {
        return 'convert';
    }

    public function data(): ?array
    {
        return null;
    }
}
