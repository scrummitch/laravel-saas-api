<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrganizationApiResource;
use App\Models\Management\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $organization = new Organization;
        $organization->name = 'New Org '.now()->toDateString();
        $organization->save();

        $organization->users()->attach($user, ['role' => 'owner']);

        $user->last_organization_id = $organization->id;
        $user->save();

        $resource = new OrganizationApiResource($organization);

        return $resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        $resource = new OrganizationApiResource($organization);

        return $resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organization $organization)
    {
        $validated = $request
            ->merge([
                'timezone' => $request->filled('timezone')
                    ? Carbon::createFromFormat('P', $request->get('timezone'))->format('P')
                    : null,
                'flags' => [
                    'has_completed_onboarding' => $request->boolean('flags.has_completed_onboarding', false),
                ],
            ])
            ->validate([
            'name' => [
                'nullable',
                'string',
            ],
            'timezone' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    try {
                        new \DateTimeZone($value);
                        return true;
                    } catch (\Exception $e) {
                        $fail('The timezone is invalid');
                    }
                },
            ],
            'flags.has_completed_onboarding' => [
                'boolean',
            ],
        ]);

        if (Arr::has($validated, 'name')) {
            $organization->name = $validated['name'];
        }

        if (Arr::has($validated, 'timezone')) {
            $organization->timezone = $validated['timezone'];
        }

        if (Arr::has($validated, 'flags')) {
            $flags = $organization->flags ?? [];
            $organization->flags = array_merge($flags, Arr::get($validated, 'flags'));
        }

        $organization->save();

        return new OrganizationApiResource($organization);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        //
    }
}
