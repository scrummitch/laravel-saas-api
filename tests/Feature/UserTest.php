<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_create_user(): void
    {
        /* @var User $user */
        $user = User::factory()
            ->unverified()
            ->create();

        $this->assertNotNull($user->id);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false);

        $user->markEmailAsVerified();
        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());

        $this->assertNull($user->organization);

        $org = $this->createOrg();
        $user->organizations()->attach($org);
        $this->assertCount(1, $user->organizations);
        $this->assertCount(1, $user->memberships);
    }

    public function test_login()
    {
        $user = User::factory()->create();
        $response = $this->postJson('/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(204);

        $this->assertAuthenticated();

        $response = $this->getJson('/users/me');
    }
}
