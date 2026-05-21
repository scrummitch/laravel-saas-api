<?php

namespace Tests\Helper;

use App\Models\Management\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;

trait UserHelper
{
    use WithFaker;

    public function createUser(array $userAttributes = [], ?Organization $organization = null): User
    {
        $org = $organization ?? $this->createOrg($this->faker->company);
        $user = User::factory()->create($userAttributes);
        $org->users()->save($user, ['role' => 'owner']);

        return $user;
    }

    public function createOrg($name = null): Organization
    {
        $factory = Organization::factory();

        return match ($name) {
            'flindev' => $this->flindev(),
            default => $factory->afterCreating(function ($org) {
                $user = User::factory()->create();
                $org->users()->save($user, ['role' => 'owner']);
            })->create()
        };
    }
}
