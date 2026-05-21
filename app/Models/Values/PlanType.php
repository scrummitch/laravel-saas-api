<?php

namespace App\Models\Values;

enum PlanType: string
{
    /**
     * Custom price created for specific clients
     */
    case custom = 'custom';

    /**
     * Standard plans included in public packages
     */
    case standard = 'standard';

    /**
     * Custom price for a "standard" plan
     * Likely used for discounting
     */
    case offer = 'offer';

    /**
     * Free plan
     */
    case provisional = 'provisional';

    /**
     * Addon to a standard plan
     */
    case addon = 'addon';
}
