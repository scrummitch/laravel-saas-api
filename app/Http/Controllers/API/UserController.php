<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\User\ChangeUserPasswordRequest;
use App\Http\Requests\API\User\UpdateUserRequest;
use App\Http\Resources\MeResource;
use App\Models\Management\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function show(Request $request)
    {
        return new MeResource($request->user());
    }

    public function update(UpdateUserRequest $request)
    {
        /* @var User $user */
        $user = $request->user();

        $user->fill(
            $request->only(['first_name', 'last_name', 'email', 'timezone'])
        );

        if ($request->has('last_organization_id')) {
            $user->last_organization_id = Organization::retrieve($request->last_organization_id)?->id;
            logger()->debug('last_organization_id', ['last_organization_id' => $user->last_organization_id]);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            $user->sendEmailVerificationNotification();
        }

        $user->save();

        return new MeResource($user);
    }

    public function changePassword(ChangeUserPasswordRequest $request)
    {
        /** @var User */
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'errors' => [
                    'current_password' => ['Incorrect current password'],
                ],
            ], 422);
        }

        $user->password = bcrypt($request['password']);
        $user->save();

        return new MeResource($user);
    }

    public function storeOrganization(Request $request)
    {
        $validated = $request->validate([
            'invite_code' => [
                'required',
                Rule::exists(Organization::class, 'invite_code'),
            ],
        ]);

        $user = $request->user();

        $organization = Organization::query()
            ->where([
                'invite_code' => $request->get('invite_code'),
            ])
            ->first();

        // if user is part of org, return a validation error
        if ($user->organizations()->where('organization_id', $organization->id)->exists()) {
            return response()->json([
                'errors' => [
                    'invite_token' => ['User is already part of this organization'],
                ],
            ], 422);
        }

        $organization->users()->attach($user, ['role' => 'member']);

        // switch to new org
        $user->last_organization_id = $organization->id;
        $user->save();

        return new MeResource($user);
    }
}
