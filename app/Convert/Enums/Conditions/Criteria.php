<?php

namespace App\Convert\Enums\Conditions;

enum Criteria: string
{
    case ScheduleQuantity = 'scheduleQuantity';
    case SubscribedToPlan = 'isSubscribedToPlan';
    case HasEntitlement = 'hasEntitlement';
    case ProductSubscriptions = 'productSubscriptions';
    case SubscribedToPackage = 'isSubscribedToPackage';

    /** @deprecated */
    case SubscribedToProduct = 'isSubscribedToProduct';
}
