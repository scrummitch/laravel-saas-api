<?php

namespace App\Billing;

enum BillingService: string
{
    case Stripe = 'stripe';
}
