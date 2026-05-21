<?php

namespace Tests\Feature;

use App\Models\Management\Organization;
use App\Models\User;
use App\Models\Values\MembershipType;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_creation_of_org(): void
    {
        $org = Organization::factory()
            ->create();

        $org = $org->fresh();

        $this->assertNotNull($org);
        $this->assertNotNull($org->name);
        //        $this->assertSame('UTC', $org->timezone);
        //        $this->assertSame('en_US', $org->document_locale);

        $user = $org->users()->first();
        $this->assertNull($user);

        $user = User::factory()
            ->create();
        $org->users()->attach($user, ['role' => MembershipType::member->value]);
        $this->assertCount(1, $org->users);

        //        $org->addUser($user, MembershipType::admin);
    }
}
