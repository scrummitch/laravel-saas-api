<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Billing\BillingProvider;

class ListIntegrationsController extends Controller
{
    public function __invoke(): array
    {
        return [
            'data' => [
                BillingProvider::integrationDescriptor('stripe'),
                BillingProvider::integrationDescriptor('stripe_test'),
            ],
        ];
    }
}
