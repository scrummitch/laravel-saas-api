<?php

namespace App\Models\Account;

enum SignalID: int
{
    case Convert = 1;        // Trial to paid on same plan
    case Switch = 2;         // Plan changed during trial
    case Upgrade = 3;        // Move to higher tier
    case Downgrade = 4;      // Move to lower tier
    case Expansion = 5;      // Increase in usage limits
    case Contraction = 6;    // Decrease in usage limits
    case Cancel = 7;         // Subscription cancelled
    case Abandon = 8;        // Never convert from trial
    case Reactivate = 9;     // Return after failed trial convert
    case Pause = 10;         // Temporary subscription hold
    case Resume = 11;        // Temporary subscription continue
    case Order = 12;         // One-time purchase
    case Return = 13;        // Money back process
    case Attach = 14;        // Addon attached
    case Detach = 15;        // Remove addon
    case Preauthorize = 16;  // Payment Method validation
    case Renewal = 17;       // Subscription renewal
    case Invoicing = 18;     // Out of renewal invoice created
    case Acquisition = 19;   // Direct paid subscription without trial
    case Resurrection = 20;  // Return long after trial abandon

    // TODO: Freemium: moving onto a forever free plan

    public function getCategory(): string
    {
        return match($this) {
            self::Convert, self::Acquisition, self::Resurrection => 'conversion',
            self::Switch, self::Upgrade, self::Downgrade => 'plan_change',
            self::Expansion, self::Contraction => 'usage_change',
            self::Cancel, self::Abandon => 'churn',
            self::Pause, self::Resume => 'subscription_state',
            self::Order, self::Return => 'transaction',
            self::Attach, self::Detach => 'addon',
            self::Preauthorize => 'payment',
            self::Renewal, self::Invoicing => 'billing',
        };
    }
}
