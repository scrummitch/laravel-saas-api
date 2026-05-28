<?php

namespace App\Http\Controllers\API;

use App\Billing\ISO4217;
use App\Http\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\Api\PlanApiResource;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Values\PlanStatus;
use App\Models\Values\PlanType;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PlansController extends Controller
{
    use ResolvesPerPage;

    public function __construct()
    {
        $this->authorizeResource(Plan::class, 'plan');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        /* @var Organization $org */
        $org = auth()->user()->currentOrganization;


        $perPage = $this->perPage($request);

        $plans = $org
            ->plans()
            ->with([
//                'schedules',
//                'schemes',
                'charges',
                'charges.metric',
                'inclusions',
                'inclusions.product',
                'inclusions.product.productFamily',
                'package',
                'package.plans',
            ])
            ->withCount([
                'schedules',
            ])
            // put active plans on top of the list
            ->orderByRaw(
                "CASE WHEN status = '".PlanStatus::Active->value."' THEN 1 ELSE 2 END"
            )
            ->orderBy('schedules_count', 'desc')
            ->paginate($perPage);

        return PlanApiResource::collection($plans);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlanRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Plan $plan)
    {
        $plan->loadMissing([
            'package',
            'inclusions',
            'inclusions.charge',
            'inclusions.charge.twins',
            'inclusions.feature',
            'inclusions.metric',
            'inclusions.product',
            'inclusions.product.productFamily',
        ]);

        return new PlanApiResource($plan);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        $values = Arr::only($request->validated(), [
            'type',
            'display_name',
            'description',
            'name',
            'billing_anchor',
            'currency',
            'invoice_interval',
        ]);

        Plan::unguard();

        if ($type = $request->validated('type')) {
            $plan->type = PlanType::from($type);
        }

        if ($currency = $request->validated('currency')) {
            $plan->currency = ISO4217::make($currency);
        }

//        if ($renew_interval = $request->validated('renew_interval')) {
//            $plan->renew_interval = CarbonInterval::create($renew_interval);
//        }

        if ($invoice_interval = $request->validated('invoice_interval')) {
            $plan->invoice_interval = CarbonInterval::create($invoice_interval);
        }

        $plan->fill($values);

        $plan->save();

        Plan::reguard();

        return new PlanApiResource($plan);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        //
    }
}
