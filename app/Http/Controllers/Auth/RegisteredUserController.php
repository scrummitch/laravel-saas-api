<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Management\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Password::defaults()],
            'invite_code' => [
                'required',
                function ($attr, $value, $fail) {
                    if ($value === 'mellon') {
                        return;
                    }

                    if (Organization::query()->where('invite_code', $value)->exists()) {
                        return;
                    }

                    $fail('Invalid invite code');
                },
            ],
        ]);

        if (Arr::get($validated, 'invite_code') === 'mellon') {
            $org = new Organization;
            $org->name = 'New Organization';
            $org->save();
        } else {
            $org = Organization::query()
                ->where('invite_code', Arr::get($validated, 'invite_code'))
                ->firstOrFail();
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $org->users()->attach($user, [
            'role' => 'owner',
        ]);

        //        event(new Registered($user));

        Auth::login($user);

        return response()->noContent();
    }
}
