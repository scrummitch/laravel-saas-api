<?php

namespace App\Convert\Events;

use App\Models\Convert\PaywallSession;

/**
 * @property PaywallSession $session
 */
interface PaywallSessionEventInterface
{
    public function name(): string;

    public function data(): ?array;
}
