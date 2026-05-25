<?php

namespace App\Http\Controllers\API;

use App\Http\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PlanApiResource;
use App\Http\Resources\Api\SchemeApiResource;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchemePlansController extends Controller
{
    use ResolvesPerPage;

    public function index(Scheme $scheme, Request $request)
    {
        $query = $scheme
            ->plans()
            ->with(['charges', 'charges.product', 'package'])
            ->withCount('schedules')
            ->latest()
            ->paginate($this->perPage($request));

        return PlanApiResource::collection($query);
    }

    public function store(Scheme $scheme, Request $request)
    {
        $validated = $request->validate([
            'plan' => [
                'required_without:product',
                function ($attribute, $value, $fail) {
                    //
                },
            ],
        ]);

        $org = $request->user()->currentOrganization;

        /* @var Plan $plan */
        $plan = $org
            ->plans()
            ->where('lookup_key', $validated['plan'])
            ->first();

        $product = $plan->charges->first()?->product;
        $packageName = $product?->name ?? 'Standard';

        $package = $scheme
            ->packages()
            ->where('product_id', $product?->id)
            ->first();

        if (! is_null($package)) {
            // update existing
            DB::table('pricing_packages')
                ->where(['id' => $package->id])
                ->update([
                    'name' => $package,
                ]);
        } else {
            $insert = [
                'name' => $package,
            ];
            DB::table('pricing_packages')
                ->insert($insert);


            try {
                $plan->package_id = $package->id;
                $plan->save();
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                logger()->warning(logname('unique_constraint'), [
                    'message' => 'Tried to attach plan to scheme, but it already exists',
                    'scheme' => $scheme->id,
                    'plan' => $plan->id,
                    'insert' => $insert,
                ]);
            }
        }

        $scheme = $scheme->loadMissing(['plans', 'packages', 'packages.product', 'plans.charges']);

        return new SchemeApiResource($scheme);
    }

    public function destroy(Scheme $scheme, Plan $plan, Request $request)
    {
        //        $scheme->plans()->detach($plan);

        return response()->noContent();
    }

    public function replace(Scheme $scheme, Request $request)
    {
        $validated = $request->validate([
            'plans.*' => [
                function ($attribute, $value, $fail) {
                    return Plan::retrieve($value['plan']) === null
                        ? $fail('Invalid plan')
                        : null;
                },
            ],
        ]);

        $plans = collect($validated['plans'])
            ->map(function ($plan) {
                return Plan::retrieve($plan['plan']);
            });
    }
}
