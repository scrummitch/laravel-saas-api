<?php

namespace App\Models\Convert;

enum CheckoutState: string
{
    case CREATED = 'created';
    case STARTED = 'started';
    case ABANDONED = 'abandoned';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function isFinalised(): bool
    {
        return in_array($this->value, [
            self::COMPLETED,
            self::EXPIRED,
            self::CANCELLED,
            self::FAILED,
        ]);
    }
}
