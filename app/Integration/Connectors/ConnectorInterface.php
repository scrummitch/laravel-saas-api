<?php

namespace App\Integration\Connectors;

use App\Models\Account\Customer;

interface ConnectorInterface {

    public function syncSubscriptions(Customer $customer): void;
}
