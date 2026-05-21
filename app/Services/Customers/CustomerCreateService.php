<?php

namespace App\Services\Customers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Account\Customer;
use App\Models\Management\Organization;
use App\Services\BaseService;
use App\Services\Result;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomerCreateService extends BaseService
{
    /**
     * @var Result <Customer>
     */
    protected Result $result;

    public function fromRequest(StoreCustomerRequest $request): ?Customer
    {
        return $this($request->user()->currentOrganization, $request->validated());
    }

    public function __invoke(Organization $organization, array $attrs): ?Customer
    {
        $customer = new Customer;

        $customer->organization_id = $organization->id;
        $customer->name = Arr::get($attrs, 'name');
        $customer->email = Arr::get($attrs, 'email');
        $customer->reference_id = Arr::get($attrs, 'reference_id');
        $customer->billing_provider_id = $organization->live_billing_provider_id;
        $customer->save();

        return $customer;
    }
}
