<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\Api\CustomerApiResource;
use App\Models\Account\Customer;
use App\Services\Customers\CustomerCreateService;

class CustomersController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Customer::class, 'customer');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $org = auth()->user()->currentOrganization;

        $query = $org
            ->customers()
            ->with(['subscriptions', 'subscriptions.plan', 'twin', 'twin.connector'])
            ->latest('reference_created_at');

        $totalCustomers = $org->customers()->count();

        return CustomerApiResource::collection($query->paginate())
            ->additional([
                'total' => $totalCustomers,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request, CustomerCreateService $createCustomer)
    {
        $customer = $createCustomer->fromRequest($request);

        return new CustomerApiResource($customer);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $customer->loadMissing([
            'subscriptions',
            'subscriptions.plan',
            'subscriptions.twin',
            'schedule.plans.charges',
            'twin',
        ]);

        return new CustomerApiResource($customer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->syncCustomer();
        $customer->syncSchedule();

        return new CustomerApiResource($customer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //
    }
}
